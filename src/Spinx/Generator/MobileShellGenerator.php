<?php

declare(strict_types=1);

namespace Spinx\Generator;

/**
 * Backs `spinx build:mobile --android|--ios` (build spec §10.1, Path A).
 *
 * This generator does NOT invoke gradle or xcodebuild — neither Android
 * Studio's toolchain nor Xcode can be assumed to be installed wherever
 * this command runs (same reasoning as `spinx preview`: orchestrate real
 * platform tooling that has to live on the developer's own machine,
 * don't try to reimplement it). It scaffolds a real, buildable project
 * with the backend URL already wired in, then tells the developer
 * exactly what to run next.
 */
final class MobileShellGenerator extends AbstractGenerator
{
    private const STUBS_ROOT = __DIR__ . '/stubs/mobile';

    /** @return array{files: string[], nextSteps: string} */
    public function generateAndroid(string $backendUrl, string $appName = 'Spinx App'): array
    {
        $targetDir = $this->projectRoot . '/mobile/android';
        $sourceDir = self::STUBS_ROOT . '/android';

        $files = $this->copyTemplateTree($sourceDir, $targetDir, [
            '{{BACKEND_URL}}' => $backendUrl,
            '{{APP_NAME}}' => $appName,
        ]);

        $nextSteps = <<<TEXT
            Android shell scaffolded at mobile/android/, pointed at {$backendUrl}.

            Next steps (requires Android Studio / the Android SDK — not
            something Spinx can install for you, same as any React Native
            or Capacitor project):
              1. Open mobile/android/ in Android Studio, or run:
                 cd mobile/android && ./gradlew assembleDebug
              2. Optional native bridge: build tools/mobile-shell/bridge/
                 with gomobile, drop the .aar into mobile/android/app/libs/,
                 then uncomment the dependency line in app/build.gradle.kts
                 (see that directory's own README for exact commands).
            TEXT;

        return ['files' => $files, 'nextSteps' => $nextSteps];
    }

    /** @return array{files: string[], nextSteps: string} */
    public function generateIos(string $backendUrl, string $appName = 'Spinx App'): array
    {
        $targetDir = $this->projectRoot . '/mobile/ios';
        $sourceDir = self::STUBS_ROOT . '/ios';

        $files = $this->copyTemplateTree($sourceDir, $targetDir, [
            '{{BACKEND_URL}}' => $backendUrl,
            '{{APP_NAME}}' => $appName,
        ]);

        $nextSteps = <<<TEXT
            iOS shell scaffolded at mobile/ios/, pointed at {$backendUrl}.

            Next steps (requires macOS + Xcode — Apple's platform
            constraint, not Spinx's, same as any React Native or
            Capacitor project):
              1. Install XcodeGen if you don't have it: brew install xcodegen
              2. cd mobile/ios && xcodegen generate
              3. open SpinxShell.xcodeproj
              4. Optional native bridge: build tools/mobile-shell/bridge/
                 with gomobile, add the resulting .xcframework to the
                 Xcode project (see that directory's own README).
            TEXT;

        return ['files' => $files, 'nextSteps' => $nextSteps];
    }

    /**
     * @param array<string, string> $replacements
     * @return string[] Absolute paths of every file written
     */
    private function copyTemplateTree(string $sourceDir, string $targetDir, array $replacements): array
    {
        if (is_dir($targetDir)) {
            throw new \RuntimeException(sprintf(
                'Mobile shell already exists at %s — remove it first if you want to regenerate.',
                $targetDir
            ));
        }

        $written = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relativePath = substr((string) $item->getPathname(), strlen($sourceDir) + 1);
            $destination = $targetDir . '/' . $relativePath;

            if ($item->isDir()) {
                continue; // Directories are created implicitly by writeFile() below.
            }

            $contents = file_get_contents((string) $item->getPathname());
            if ($contents === false) {
                throw new \RuntimeException("Failed to read stub: {$item->getPathname()}");
            }

            $this->writeFile($destination, strtr($contents, $replacements));
            $written[] = $destination;
        }

        return $written;
    }
}
