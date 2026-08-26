<?php

declare(strict_types=1);

namespace Spinx\Ai\Guard;

use Spinx\Ai\Continuity\ContinuityTracker;

/**
 * Security and usage guard protecting the Spinx AI Framework Builder from misuse.
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
