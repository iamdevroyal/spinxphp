<?php

declare(strict_types=1);

namespace Spinx\Installer;

/**
 * Interactive first-run installer — triggered automatically by Composer's
 * post-create-project-cmd hook when a developer runs:
 *
 *   composer create-project spinxphp/framework my-app
 *
 * Guides the developer through 5 setup questions (app name, frontend, DB
 * driver, runtime driver, app URL), writes a configured .env, updates
 * spinx.json, and then — crucially — downloads the RoadRunner binary
 * automatically via `vendor/bin/rr get` so the developer never has to
 * know that step exists.
 *
 * Design rules:
 *  - Zero external dependencies — uses only standard PHP streams (STDIN)
 *    and proc_open for child processes.
 *  - Non-interactive safe: If running in CI or piped mode (feof(STDIN)),
 *    immediately defaults all choices without blocking.
 *  - Never throws — all failures print a friendly message and continue.
 *  - ANSI colour output on terminals that support it.
 */
final class Installer
{
    private bool $ansi;

    public function __construct(
        private readonly string $projectRoot,
    ) {
        $this->ansi = !isset($_SERVER['NO_COLOR'])
            && function_exists('posix_isatty')
            && @posix_isatty(STDOUT);
    }

    public static function run(): void
    {
        $cwd = (string) getcwd();
        if (is_file($cwd . '/spinx.json') || is_file($cwd . '/.env.example') || is_file($cwd . '/composer.json')) {
            $root = $cwd;
        } else {
            // Fallback to relative parent if run from deep within vendor
            $root = is_file(dirname(__DIR__, 3) . '/spinx.json')
                ? dirname(__DIR__, 3)
                : dirname(__DIR__, 4);
        }

        (new self($root))->install();
    }

    public function install(): void
    {
        $this->printBanner();

        // ── 1. App Name ─────────────────────────────────────────────────
        $defaultAppName = ucfirst(basename($this->projectRoot));
        $appName = $this->ask(
            question: '  What is the name of your application?',
            default: $defaultAppName,
        );

        // ── 2. Frontend ──────────────────────────────────────────────────
        $frontend = $this->choice(
            question: '  Which frontend adapter would you like to use?',
            choices:  ['Vue 3 (default)', 'React 19'],
            default:  0,
        );
        $frontendKey = $frontend === 0 ? 'vue' : 'react';

        // ── 3. Database Driver ───────────────────────────────────────────
        $dbChoice = $this->choice(
            question: '  Which database driver would you like to use?',
            choices:  ['SQLite — zero-config, perfect for local dev (default)', 'MySQL', 'PostgreSQL'],
            default:  0,
        );
        $dbConfig = match ($dbChoice) {
            1 => $this->askDatabaseCredentials('MySQL', '3306'),
            2 => $this->askDatabaseCredentials('PostgreSQL', '5432'),
            default => ['driver' => 'pdo_sqlite', 'host' => '', 'port' => '', 'database' => '', 'username' => '', 'password' => ''],
        };

        // ── 4. Runtime Driver ────────────────────────────────────────────
        $runtimeChoice = $this->choice(
            question: '  Which runtime driver would you like to use?',
            choices:  ['RoadRunner (recommended — works on Windows/Linux/macOS)', 'Swoole (Linux/Docker only)'],
            default:  0,
        );
        $runtimeDriver = $runtimeChoice === 0 ? 'roadrunner' : 'swoole';

        // ── 5. App URL ───────────────────────────────────────────────────
        $defaultPort = $runtimeDriver === 'swoole' ? '9501' : '8080';
        $appUrl = $this->ask(
            question: '  What is your application URL?',
            default: "http://localhost:{$defaultPort}",
        );

        // ── Write .env ───────────────────────────────────────────────────
        $this->newLine();
        $this->writeln($this->dim('  ◌ Writing .env...'));
        $this->writeEnv($appName, $appUrl, $dbConfig, $runtimeDriver);
        $this->writeln($this->green('  ✓ .env configured'));

        // ── Update spinx.json ────────────────────────────────────────────
        $this->writeln($this->dim('  ◌ Updating spinx.json...'));
        $this->updateSpinxJson($runtimeDriver, $frontendKey);
        $this->writeln($this->green('  ✓ spinx.json updated'));

        // ── Download RoadRunner (automatic) ─────────────────────────────
        if ($runtimeDriver === 'roadrunner') {
            $this->newLine();
            $this->writeln($this->dim('  ◌ Downloading RoadRunner server binary (automatic via rr get)...'));
            $rrDownloaded = $this->downloadRoadRunner();
            if ($rrDownloaded) {
                $this->writeln($this->green('  ✓ RoadRunner binary ready'));
            } else {
                $this->writeln($this->yellow('  ⚠ RoadRunner download skipped/failed — run `vendor/bin/rr get` manually'));
            }
        }

        // ── Frontend npm setup ───────────────────────────────────────────
        $this->newLine();
        $installNpm = $this->confirm(
            question: '  Install frontend npm dependencies now? (requires Node.js)',
            default: true,
        );

        if ($installNpm) {
            $frontendDir = $this->projectRoot . '/frontend';
            if (is_dir($frontendDir) && is_file($frontendDir . '/package.json')) {
                $this->writeln($this->dim('  ◌ Running npm install in frontend/...'));
                $npmOk = $this->runSubprocess(['npm', 'install'], $frontendDir);
                if ($npmOk) {
                    $this->writeln($this->green('  ✓ npm dependencies installed'));
                } else {
                    $this->writeln($this->yellow('  ⚠ npm install failed — run `cd frontend && npm install` manually'));
                }
            } else {
                $this->writeln($this->yellow('  ⚠ frontend/package.json not found — skipping npm install'));
            }
        }

        // ── Run Migrations ───────────────────────────────────────────────
        $this->newLine();
        $runMigrations = $this->confirm(
            question: '  Run database migrations now?',
            default: true,
        );

        if ($runMigrations) {
            $this->writeln($this->dim('  ◌ Running migrations...'));
            $migrateOk = $this->runSubprocess(
                ['php', 'spinx', 'migrate'],
                $this->projectRoot,
            );
            if ($migrateOk) {
                $this->writeln($this->green('  ✓ Migrations complete'));
            } else {
                $this->writeln($this->yellow('  ⚠ Migrations skipped/failed — run `php spinx migrate` after configuring database'));
            }
        }

        // ── Done ─────────────────────────────────────────────────────────
        $this->printSummary($appName, $appUrl, $frontendKey, $runtimeDriver, $installNpm, $runMigrations);
    }

    // ────────────────────────────────────────────────────────────────────
    // Questions
    // ────────────────────────────────────────────────────────────────────

    private function ask(string $question, string $default = ''): string
    {
        $prompt = $default !== ''
            ? $this->bold($question) . $this->dim(" [{$default}]") . ' › '
            : $this->bold($question) . ' › ';

        $this->write($prompt);

        if ($this->isNonInteractive()) {
            $this->writeln($default);
            return $default;
        }

        $answer = trim((string) fgets(STDIN));

        return $answer !== '' ? $answer : $default;
    }

    /** @param string[] $choices */
    private function choice(string $question, array $choices, int $default = 0): int
    {
        $this->writeln($this->bold($question));
        foreach ($choices as $idx => $label) {
            $marker = $idx === $default ? $this->green('❯') : ' ';
            $this->writeln("  {$marker} [{$idx}] {$label}");
        }

        $this->write($this->dim("  Enter number [default: {$default}]: "));

        if ($this->isNonInteractive()) {
            $this->writeln((string) $default);
            return $default;
        }

        $raw = trim((string) fgets(STDIN));

        if ($raw === '') {
            return $default;
        }

        $num = filter_var($raw, FILTER_VALIDATE_INT);
        if ($num !== false && isset($choices[$num])) {
            return $num;
        }

        $this->writeln($this->yellow("  Invalid choice — using default ({$default})"));
        return $default;
    }

    private function confirm(string $question, bool $default = true): bool
    {
        $hint = $default ? '[Y/n]' : '[y/N]';
        $this->write($this->bold($question) . ' ' . $this->dim($hint) . ' ');

        if ($this->isNonInteractive()) {
            $this->writeln($default ? 'yes' : 'no');
            return $default;
        }

        $raw = strtolower(trim((string) fgets(STDIN)));

        if ($raw === '') {
            return $default;
        }

        return in_array($raw, ['y', 'yes'], true);
    }

    private function isNonInteractive(): bool
    {
        if (!defined('STDIN') || !is_resource(STDIN)) {
            return true;
        }

        if (feof(STDIN)) {
            return true;
        }

        return false;
    }

    /** @return array{driver: string, host: string, port: string, database: string, username: string, password: string} */
    private function askDatabaseCredentials(string $label, string $defaultPort): array
    {
        $this->writeln($this->dim("  ── {$label} connection details ──"));
        $driver   = $label === 'MySQL' ? 'pdo_mysql' : 'pdo_pgsql';
        $host     = $this->ask('  Database host', '127.0.0.1');
        $port     = $this->ask('  Database port', $defaultPort);
        $database = $this->ask('  Database name', 'spinx');
        $username = $this->ask('  Database username', 'root');
        $password = $this->ask('  Database password', '');

        return compact('driver', 'host', 'port', 'database', 'username', 'password');
    }

    // ────────────────────────────────────────────────────────────────────
    // File writers
    // ────────────────────────────────────────────────────────────────────

    /** @param array{driver: string, host: string, port: string, database: string, username: string, password: string} $db */
    private function writeEnv(string $appName, string $appUrl, array $db, string $driver): void
    {
        $examplePath = $this->projectRoot . '/.env.example';
        $envPath     = $this->projectRoot . '/.env';

        $template = is_file($examplePath) ? (string) file_get_contents($examplePath) : '';

        $replacements = [
            '/^APP_NAME=.*/m'     => 'APP_NAME="' . addslashes($appName) . '"',
            '/^APP_URL=.*/m'      => 'APP_URL=' . $appUrl,
            '/^SPINX_DRIVER=.*/m' => 'SPINX_DRIVER=' . $driver,
            '/^DB_DRIVER=.*/m'    => 'DB_DRIVER=' . $db['driver'],
            '/^DB_HOST=.*/m'      => 'DB_HOST=' . $db['host'],
            '/^DB_PORT=.*/m'      => 'DB_PORT=' . $db['port'],
            '/^DB_DATABASE=.*/m'  => 'DB_DATABASE=' . $db['database'],
            '/^DB_USERNAME=.*/m'  => 'DB_USERNAME=' . $db['username'],
            '/^DB_PASSWORD=.*/m'  => 'DB_PASSWORD=' . $db['password'],
        ];

        foreach ($replacements as $pattern => $replacement) {
            $template = preg_replace($pattern, $replacement, $template) ?? $template;
        }

        file_put_contents($envPath, $template);
    }

    private function updateSpinxJson(string $driver, string $frontend): void
    {
        $path = $this->projectRoot . '/spinx.json';

        if (!is_file($path)) {
            return;
        }

        $config = json_decode((string) file_get_contents($path), true) ?? [];
        $config['driver']   = $driver;
        $config['frontend'] = $frontend;

        file_put_contents($path, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    }

    // ────────────────────────────────────────────────────────────────────
    // RoadRunner auto-download
    // ────────────────────────────────────────────────────────────────────

    private function downloadRoadRunner(): bool
    {
        // Check if rr binary already exists in project root
        $rrBinary = $this->projectRoot . (PHP_OS_FAMILY === 'Windows' ? '/rr.exe' : '/rr');
        if (is_file($rrBinary)) {
            $this->writeln($this->dim('  (RoadRunner server binary already present in project root)'));
            return true;
        }

        $rrCli = $this->projectRoot . '/vendor/bin/rr';

        if (!is_file($rrCli) && !is_file($rrCli . '.bat') && !is_file($rrCli . '.exe')) {
            $this->writeln($this->yellow('  vendor/bin/rr not found — run `vendor/bin/rr get` manually'));
            return false;
        }

        $binary = PHP_OS_FAMILY === 'Windows' && is_file($rrCli . '.bat') ? $rrCli . '.bat' : $rrCli;

        return $this->runSubprocess([$binary, 'get'], $this->projectRoot);
    }

    /** @param string[] $cmd */
    private function runSubprocess(array $cmd, string $cwd): bool
    {
        $process = proc_open($cmd, [STDIN, STDOUT, STDERR], $pipes, $cwd);

        if ($process === false) {
            return false;
        }

        return proc_close($process) === 0;
    }

    // ────────────────────────────────────────────────────────────────────
    // Output helpers
    // ────────────────────────────────────────────────────────────────────

    private function printBanner(): void
    {
        $this->newLine();
        $this->writeln($this->bold($this->pink('  ░ Spinx Framework — Interactive Installer')));
        $this->writeln($this->dim('  ─────────────────────────────────────────────'));
        $this->newLine();
        $this->writeln($this->dim('  Answer a few questions to configure your new project.'));
        $this->writeln($this->dim('  Press [Enter] to accept the default shown in [brackets].'));
        $this->newLine();
    }

    private function printSummary(
        string $appName,
        string $appUrl,
        string $frontend,
        string $driver,
        bool $npmInstalled,
        bool $migrated,
    ): void {
        $this->newLine();
        $this->writeln($this->bold($this->green('  ✓ Your Spinx application is ready!')));
        $this->newLine();
        $this->writeln($this->dim('  ─────────────────────────────────────────────'));
        $this->writeln("    App name  : {$appName}");
        $this->writeln("    URL       : {$appUrl}");
        $this->writeln("    Frontend  : {$frontend}");
        $this->writeln("    Runtime   : {$driver}");
        $this->writeln($this->dim('  ─────────────────────────────────────────────'));
        $this->newLine();
        $this->writeln($this->bold('  Next steps:'));

        if (!$npmInstalled) {
            $this->writeln($this->dim('    cd frontend && npm install && cd ..'));
        }
        if (!$migrated) {
            $this->writeln($this->dim('    php spinx migrate'));
        }

        $this->writeln($this->pink('    php spinx serve'));
        $this->newLine();
        $this->writeln($this->dim('  Docs: https://spinx.dev/docs'));
        $this->writeln($this->dim('  GitHub: https://github.com/iamdevroyal/spinxphp'));
        $this->newLine();
    }

    private function write(string $text): void
    {
        fwrite(STDOUT, $text);
    }

    private function writeln(string $text): void
    {
        fwrite(STDOUT, $text . PHP_EOL);
    }

    private function newLine(): void
    {
        fwrite(STDOUT, PHP_EOL);
    }

    private function bold(string $text): string
    {
        return $this->ansi ? "\033[1m{$text}\033[0m" : $text;
    }

    private function dim(string $text): string
    {
        return $this->ansi ? "\033[2m{$text}\033[0m" : $text;
    }

    private function green(string $text): string
    {
        return $this->ansi ? "\033[32m{$text}\033[0m" : $text;
    }

    private function yellow(string $text): string
    {
        return $this->ansi ? "\033[33m{$text}\033[0m" : $text;
    }

    private function pink(string $text): string
    {
        return $this->ansi ? "\033[38;5;205m{$text}\033[0m" : $text;
    }
}
