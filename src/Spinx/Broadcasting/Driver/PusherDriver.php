<?php

declare(strict_types=1);

namespace Spinx\Broadcasting\Driver;

use Spinx\Log\Log;
use Spinx\Support\Config;

/**
 * Universal Pusher protocol broadcast driver.
 * Fully compatible with Pusher Cloud, Soketi (Docker/Node/Standalone), and Laravel Reverb.
 */
final class PusherDriver implements BroadcastDriverInterface
{
    private string $appId;
    private string $key;
    private string $secret;
    private string $host;
    private int $port;
    private string $scheme;

    public function __construct(?array $config = null)
    {
        $cfg = $config ?? (array) Config::get('broadcasting.connections.pusher', []);

        $this->appId  = (string) ($cfg['app_id'] ?? env('PUSHER_APP_ID', ''));
        $this->key    = (string) ($cfg['key'] ?? env('PUSHER_APP_KEY', ''));
        $this->secret = (string) ($cfg['secret'] ?? env('PUSHER_APP_SECRET', ''));

        $options = (array) ($cfg['options'] ?? []);
        $this->host   = (string) ($options['host'] ?? env('PUSHER_HOST', 'api.pusherapp.com'));
        $this->port   = (int) ($options['port'] ?? env('PUSHER_PORT', 443));
        $this->scheme = (string) ($options['scheme'] ?? env('PUSHER_SCHEME', 'https'));
    }

    public function broadcast(array $channels, string $event, array $payload): void
    {
        if ($this->key === '' || $this->secret === '' || $this->appId === '') {
            Log::warning('Pusher broadcasting skipped: missing API credentials.');
            return;
        }

        $body = json_encode([
            'name'     => $event,
            'channels' => array_values($channels),
            'data'     => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ], JSON_THROW_ON_ERROR);

        $path = "/apps/{$this->appId}/events";
        $params = [
            'auth_key'        => $this->key,
            'auth_timestamp'  => time(),
            'auth_version'    => '1.0',
            'body_md5'        => md5($body),
        ];

        ksort($params);
        $queryString = http_build_query($params);

        // Sign the request
        $signData = "POST\n{$path}\n{$queryString}";
        $signature = hash_hmac('sha256', $signData, $this->secret);

        $url = "{$this->scheme}://{$this->host}:{$this->port}{$path}?{$queryString}&auth_signature={$signature}";

        $this->postJson($url, $body);
    }

    public function authenticateChannel(string $channel, string $socketId, ?array $userData = null): array|false
    {
        if ($this->secret === '' || $this->key === '') {
            return false;
        }

        if (str_starts_with($channel, 'presence-')) {
            $userJson = json_encode($userData ?? ['user_id' => 'guest'], JSON_UNESCAPED_SLASHES);
            $stringToSign = "{$socketId}:{$channel}:{$userJson}";
            $signature = hash_hmac('sha256', $stringToSign, $this->secret);

            return [
                'auth'         => "{$this->key}:{$signature}",
                'channel_data' => $userJson,
            ];
        }

        $stringToSign = "{$socketId}:{$channel}";
        $signature = hash_hmac('sha256', $stringToSign, $this->secret);

        return [
            'auth' => "{$this->key}:{$signature}",
        ];
    }

    private function postJson(string $url, string $jsonBody): void
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $jsonBody,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($jsonBody),
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode >= 400) {
            Log::error("Pusher broadcast request failed [HTTP {$httpCode}]: {$error} {$response}");
        }
    }
}
