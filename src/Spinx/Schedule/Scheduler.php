<?php

declare(strict_types=1);

namespace Spinx\Schedule;

/**
 * Register tasks in schedule.php at the project root, run by `spinx
 * schedule:run` — meant to be invoked every minute by ONE real OS cron
 * entry:
 *
 *   * * * * * cd /path/to/app && php spinx schedule:run >> /dev/null 2>&1
 *
 * Same "one cron entry, the framework figures out what's actually due"
 * pattern Laravel's scheduler uses.
 *
 * NOT distributed-lock-safe: if schedule:run somehow gets invoked twice
 * within the same due minute (cron drift, a manual second run), a task
 * runs twice. No cross-run persistence tracks "already ran this minute"
 * — stated plainly as a real, deliberate scope limitation rather than
 * implied to be handled. Wrap a task's own callback in your own
 * idempotency check if this matters for it.
 */
final class Scheduler
{
    /** @var ScheduledTask[] */
    private array $tasks = [];

    /** Register a closure as a scheduled task and return it for fluent frequency setup. */
    public function call(\Closure $callback, string $description = ''): ScheduledTask
    {
        return $this->tasks[] = new ScheduledTask(
            $callback,
            $description !== '' ? $description : 'scheduled task',
        );
    }

    /** @return ScheduledTask[] All registered tasks, in registration order. */
    public function tasks(): array
    {
        return $this->tasks;
    }

    /**
     * Returns every task whose cron expression matches $now, preserving
     * registration order. Tasks that are not due are silently filtered out.
     *
     * @return ScheduledTask[]
     */
    public function dueTasks(\DateTimeImmutable $now): array
    {
        return array_values(
            array_filter(
                $this->tasks,
                static fn (ScheduledTask $task) => $task->isDue($now),
            )
        );
    }
}
