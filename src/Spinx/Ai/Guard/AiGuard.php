<?php

declare(strict_types=1);

namespace Spinx\Ai\Guard;

use Spinx\Ai\Continuity\ContinuityTracker;

/**
 * Security, usage, and architectural invariant guard protecting the Spinx AI Framework Builder from misuse.
 */
final class AiGuard
{
    public const MAX_PROMPT_LENGTH = 5000;
    public const MAX_REQUESTS_PER_HOUR = 100;

    /**
     * Validate an incoming developer prompt against safety, length, and rate limits.
     *
     * @throws \InvalidArgumentException if validation fails
     * @throws \RuntimeException if rate limit is exceeded
     */
    public static function validatePrompt(string $prompt, ContinuityTracker $continuity): void
    {
        $trimmed = trim($prompt);

        if ($trimmed === '') {
            throw new \InvalidArgumentException('Prompt cannot be empty.');
        }

        if (mb_strlen($trimmed) > self::MAX_PROMPT_LENGTH) {
            throw new \InvalidArgumentException(
                'Prompt exceeds maximum allowed length (' . self::MAX_PROMPT_LENGTH . ' characters).'
            );
        }

        self::checkRateLimit($continuity);
    }

    /**
     * Inspect a developer prompt for non-Spinx / anti-pattern requests and return actionable guidance.
     *
     * @return array<int, array{pattern: string, warning: string, guidance: string}>
     */
    public static function detectArchitecturalViolations(string $prompt): array
    {
        $violations = [];
        $lower = strtolower($prompt);

        // 1. Global Models outside DDD
        if (preg_match('/(app\/models|in app\/models|create a model in app\/models)/i', $prompt)) {
            $violations[] = [
                'pattern'  => 'app/Models/',
                'warning'  => 'Spinx enforces strict Domain-Driven Design (DDD). Loose models in app/Models/ are not permitted.',
                'guidance' => 'Models belong in app/Modules/<ModuleName>/Infrastructure/Persistence/Models/<ModelName>.php, with pure business entities in app/Modules/<ModuleName>/Domain/Entities/<EntityName>.php.',
            ];
        }

        // 2. Global Route Files
        if (preg_match('/(routes\/web\.php|routes\/api\.php|in routes\/web|in routes\/api)/i', $prompt)) {
            $violations[] = [
                'pattern'  => 'routes/web.php',
                'warning'  => 'Spinx does not use global route files like routes/web.php or routes/api.php.',
                'guidance' => 'All module routes must be declared in app/Modules/<ModuleName>/module.php using Spinx\\Routing\\Route.',
            ];
        }

        // 3. Superglobals in Persistent Runtimes
        if (str_contains($lower, '$_session') || str_contains($lower, 'session_start()')) {
            $violations[] = [
                'pattern'  => '$_SESSION / session_start()',
                'warning'  => 'Raw PHP session superglobals cause critical state leakage across requests in persistent workers (RoadRunner / Swoole).',
                'guidance' => 'Use Spinx\\Auth\\Auth facade (Auth::check(), Auth::user()) or inject Spinx\\Session\\SessionInterface.',
            ];
        }

        // 4. Laravel Service Providers / Artisan
        if (preg_match('/(serviceprovider|service provider|artisan make:|illuminate\\\\)/i', $prompt)) {
            $violations[] = [
                'pattern'  => 'Laravel ServiceProvider / Artisan',
                'warning'  => 'Spinx does not use Laravel ServiceProviders or Illuminate packages.',
                'guidance' => 'Register dependency injection services in app/Modules/<ModuleName>/module.php under the "services" key using Symfony DI ContainerBuilder.',
            ];
        }

        return $violations;
    }

    /**
     * Verify that the request rate limit for the project has not been exceeded.
     */
    public static function checkRateLimit(ContinuityTracker $continuity): void
    {
        $data = $continuity->getData();
        $history = $data['history'] ?? [];
        $oneHourAgo = time() - 3600;

        $recentCount = 0;
        foreach ($history as $entry) {
            $entryTime = strtotime($entry['timestamp'] ?? '');
            if ($entryTime >= $oneHourAgo) {
                $recentCount++;
            }
        }

        if ($recentCount >= self::MAX_REQUESTS_PER_HOUR) {
            throw new \RuntimeException(
                'Spinx AI Builder hourly rate limit exceeded (' . self::MAX_REQUESTS_PER_HOUR . ' requests/hr). Please wait a few moments.'
            );
        }
    }
}
