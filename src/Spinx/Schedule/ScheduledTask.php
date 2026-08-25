<?php

declare(strict_types=1);

namespace Spinx\Schedule;

/**
 * A single entry in the scheduler, carrying a callback, a human-readable
 * description, and a cron expression that controls when it fires.
 *
 * Fluent builder — all setters return $this so calls can be chained:
 *
 *   $scheduler->call(fn() => doWork(), 'description')
 *             ->daily('03:00');
 *
 * The cron expression defaults to every-minute (* * * * *) at construction.
 * Always call one of the frequency methods (or cron() directly) before
 * relying on isDue() — leaving the default means the task runs every minute.
 */
final class ScheduledTask
{
    private string $expression = '* * * * *';

    public function __construct(
        public readonly \Closure $callback,
        public readonly string   $description,
    ) {
    }

    /** Set an arbitrary 5-field cron expression directly. */
    public function cron(string $expression): static
    {
        $this->expression = $expression;

        return $this;
    }

    /** Run every minute — equivalent to cron('* * * * *'). */
    public function everyMinute(): static
    {
        return $this->cron('* * * * *');
    }

    /** Run every N minutes — uses cron step syntax (star-slash-N). */
    public function everyMinutes(int $n): static
    {
        return $this->cron("*/{$n} * * * *");
    }

    /** Run at the top of every hour. */
    public function hourly(): static
    {
        return $this->cron('0 * * * *');
    }

    /**
     * Run once per day at the given 24-hour time.
     *
     * @param string $time 24-hour "H:i", e.g. "14:30". Leading zeros on the
     *                     minute component are preserved in the expression
     *                     ("09:00" → "00 09 * * *"), which is functionally
     *                     identical since CronExpression does integer
     *                     comparison, not string comparison.
     */
    public function daily(string $time = '00:00'): static
    {
        [$hour, $minute] = explode(':', $time);

        return $this->cron("{$minute} {$hour} * * *");
    }

    /**
     * Run once per week on the given weekday at the given time.
     *
     * @param int    $weekday 0 (Sunday) through 6 (Saturday)
     * @param string $time    24-hour "H:i"
     */
    public function weekly(int $weekday = 1, string $time = '00:00'): static
    {
        [$hour, $minute] = explode(':', $time);

        return $this->cron("{$minute} {$hour} * * {$weekday}");
    }

    /**
     * Run once per month on the given day at the given time.
     *
     * @param int    $day  Day of month (1–31)
     * @param string $time 24-hour "H:i"
     */
    public function monthly(int $day = 1, string $time = '00:00'): static
    {
        [$hour, $minute] = explode(':', $time);

        return $this->cron("{$minute} {$hour} {$day} * *");
    }

    /** Returns true if this task is due at the given moment. */
    public function isDue(\DateTimeImmutable $now): bool
    {
        return CronExpression::matches($this->expression, $now);
    }

    public function expression(): string
    {
        return $this->expression;
    }
}
