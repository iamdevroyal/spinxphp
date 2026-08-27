<?php

declare(strict_types=1);

namespace Spinx\Broadcasting;

use Spinx\Broadcasting\Driver\BroadcastDriverInterface;
use Spinx\Broadcasting\Driver\LogDriver;
use Spinx\Broadcasting\Driver\NullDriver;
use Spinx\Broadcasting\Driver\PusherDriver;
use Spinx\Support\Config;

/**
 * Broadcast manager managing connection drivers and channel dispatching.
 */
final class BroadcastManager
{
    /** @var array<string, BroadcastDriverInterface> */
    private array $drivers = [];
    /** @var array<string, callable> */
    private static array $channelAuthCallbacks = [];

    public function __construct(
        private readonly ?string $defaultDriver = null,
    ) {
    }

    public function connection(?string $name = null): BroadcastDriverInterface
    {
        $name = $name ?? $this->getDefaultDriver();

        return $this->drivers[$name] ??= $this->resolve($name);
    }

    public function getDefaultDriver(): string
    {
        return $this->defaultDriver 
            ?? (string) Config::get('broadcasting.default', env('BROADCAST_DRIVER', 'log'));
    }

    /**
     * Broadcast to one or more public channels.
     */
    public function channel(string|array $channels): PendingBroadcast
    {
        $channelList = is_array($channels) ? $channels : [$channels];
        return new PendingBroadcast($this, $channelList);
    }

    /**
     * Broadcast to a private channel.
     */
    public function private(string $channel): PendingBroadcast
    {
        return $this->channel(new PrivateChannel($channel));
    }

    /**
     * Broadcast to a presence channel.
     */
    public function presence(string $channel): PendingBroadcast
    {
        return $this->channel(new PresenceChannel($channel));
    }

    /**
     * Broadcast an event implementing ShouldBroadcast.
     */
    public function event(ShouldBroadcast $event): void
    {
        $channels = $event->broadcastOn();
        $channelNames = [];

        if (is_array($channels)) {
            foreach ($channels as $c) {
                $channelNames[] = (string) $c;
            }
        } else {
            $channelNames[] = (string) $channels;
        }

        $eventName = $event->broadcastAs() ?? (new \ReflectionClass($event))->getShortName();
        $payload = $event->broadcastWith();

        $this->connection()->broadcast($channelNames, $eventName, $payload);
    }

    /**
     * Register a channel authorization callback.
     */
    public static function channelAuth(string $channelPattern, callable $callback): void
    {
        self::$channelAuthCallbacks[$channelPattern] = $callback;
    }

    /**
     * Authenticate a request for a private/presence channel.
     */
    public function authenticate(string $channel, string $socketId, ?object $user): array|false
    {
        // Strip private- or presence- prefix for route callback matching if needed
        $cleanName = preg_replace('/^(private|presence)-/', '', $channel);

        $authorized = false;
        $userData = null;

        foreach (self::$channelAuthCallbacks as $pattern => $callback) {
            if ($pattern === $channel || $pattern === $cleanName || $this->matchesPattern($pattern, $cleanName)) {
                $result = $callback($user, $cleanName);
                if ($result === true) {
                    $authorized = true;
                    break;
                } elseif (is_array($result)) {
                    $authorized = true;
                    $userData = $result;
                    break;
                }
            }
        }

        // If no explicit callback registered, allow if user is authenticated
        if (!$authorized && empty(self::$channelAuthCallbacks) && $user !== null) {
            $authorized = true;
        }

        if (!$authorized) {
            return false;
        }

        return $this->connection()->authenticateChannel($channel, $socketId, $userData);
    }

    private function matchesPattern(string $pattern, string $channel): bool
    {
        $regex = '#^' . preg_replace('/\\\{[a-zA-Z0-9_]+\\\}/', '([^/]+)', preg_quote($pattern, '#')) . '$#';
        return (bool) preg_match($regex, $channel);
    }

    private function resolve(string $name): BroadcastDriverInterface
    {
        $driver = Config::get("broadcasting.connections.{$name}.driver", $name);

        return match ($driver) {
            'pusher' => new PusherDriver(),
            'log'    => new LogDriver(),
            'null'   => new NullDriver(),
            default  => throw new \InvalidArgumentException("Broadcast driver [{$driver}] is not supported."),
        };
    }
}
