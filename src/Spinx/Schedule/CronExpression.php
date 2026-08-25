<?php

declare(strict_types=1);

namespace Spinx\Schedule;

/**
 * Standard 5-field cron syntax: minute hour day month weekday.
 * Supports *, exact numbers, comma lists (1,15,30), ranges (1-5), and
 * step syntax (e.g. star-slash-15 for "every 15 units"). Doesn't support
 * named months/weekdays (JAN, MON) or the more exotic extensions some
 * cron implementations add (@yearly shorthand, L for "last day of
 * month") — numeric fields only, which covers the overwhelming majority
 * of real schedules.
 *
 * Note on the docblock above: the literal characters for step syntax
 * (asterisk-slash) are spelled out in words rather than written directly
 * because asterisk-slash is PHP's block-comment terminator — writing it
 * literally would prematurely close this docblock and cause a parse
 * error. This is a real, non-obvious PHP gotcha; the wording is
 * intentional, not a typo.
 */
final class CronExpression
{
    public static function matches(string $expression, \DateTimeImmutable $now): bool
    {
        $parts = preg_split('/\s+/', trim($expression));

        if ($parts === false || count($parts) !== 5) {
            throw new \InvalidArgumentException(
                "Invalid cron expression (expected 5 fields): \"{$expression}\""
            );
        }

        [$minute, $hour, $day, $month, $weekday] = $parts;

        return self::fieldMatches($minute,  (int) $now->format('i'))
            && self::fieldMatches($hour,    (int) $now->format('G'))
            && self::fieldMatches($day,     (int) $now->format('j'))
            && self::fieldMatches($month,   (int) $now->format('n'))
            && self::fieldMatches($weekday, (int) $now->format('w'));
    }

    private static function fieldMatches(string $field, int $value): bool
    {
        foreach (explode(',', $field) as $part) {
            if ($part === '*') {
                return true;
            }

            // Step syntax: star-slash-N matches when value is divisible by N.
            if (str_starts_with($part, '*/')) {
                $step = (int) substr($part, 2);
                if ($step > 0 && $value % $step === 0) {
                    return true;
                }
                continue;
            }

            // Range syntax: start-end (inclusive on both ends).
            if (str_contains($part, '-')) {
                [$start, $end] = array_map('intval', explode('-', $part, 2));
                if ($value >= $start && $value <= $end) {
                    return true;
                }
                continue;
            }

            // Exact numeric match.
            if (ctype_digit($part) && (int) $part === $value) {
                return true;
            }
        }

        return false;
    }
}
