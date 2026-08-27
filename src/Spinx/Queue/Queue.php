<?php

declare(strict_types=1);

namespace Spinx\Queue;

use Spinx\Queue\Driver\QueueDriverInterface;

/**
 * Static facade for dispatching and inspecting asynchronous jobs.
 *
 * Usage:
 *   Queue::push(new SendEmailJob($email));
 *   Queue::onQueue('high')->withPriority(10)->push(new GenerateReportJob($id));
 *   Queue::later(300, new ReminderJob($userId));
 */
final class Queue
{
    private static ?QueueManager $manager = null;

    public static function setManager(QueueManager $manager): void
    {
        self::$manager = $manager;
    }

    public static function getManager(): QueueManager
    {
        if (self::$manager === null) {
            self::$manager = new QueueManager();
        }

        return self::$manager;
    }

    public static function connection(?string $name = null): QueueDriverInterface
    {
        return self::getManager()->connection($name);
    }

    public static function onQueue(string $queue): QueueManager
    {
        return self::getManager()->onQueue($queue);
    }

    public static function withPriority(int $priority): QueueManager
    {
        return self::getManager()->withPriority($priority);
    }

    public static function push(Job $job, ?string $queue = null, int $delaySeconds = 0, ?int $priority = null): string
    {
        return self::getManager()->push($job, $queue, $delaySeconds, $priority);
    }

    public static function later(int $delaySeconds, Job $job, ?string $queue = null): string
    {
        return self::getManager()->later($delaySeconds, $job, $queue);
    }

    public static function dispatch(Job $job, int $delaySeconds = 0, string $queue = 'default', int $priority = 0): string
    {
        return self::getManager()->dispatch($job, $delaySeconds, $queue, $priority);
    }

    public static function dispatchSync(Job $job): void
    {
        self::getManager()->dispatchSync($job);
    }

    public static function __callStatic(string $method, array $arguments): mixed
    {
        return self::getManager()->$method(...$arguments);
    }
}
