<?php

declare(strict_types=1);

namespace Spinx\Filesystem\Driver;

use Spinx\Log\Log;

/**
 * Universal S3-compatible cloud object storage driver.
 * Native AWS Signature V4 implementation works seamlessly with:
 * - Amazon Web Services (AWS S3)
 * - Cloudflare R2
 * - MinIO
 * - DigitalOcean Spaces
 * - Wasabi / Backblaze B2
 */
final class S3FilesystemDriver implements FilesystemDriverInterface
{
    private string $key;
    private string $secret;
    private string $region;
    private string $bucket;
    private ?string $endpoint;
    private ?string $urlCustom;

    public function __construct(array $config = [])
    {
        $this->key       = (string) ($config['key'] ?? env('AWS_ACCESS_KEY_ID', ''));
        $this->secret    = (string) ($config['secret'] ?? env('AWS_SECRET_ACCESS_KEY', ''));
        $this->region    = (string) ($config['region'] ?? env('AWS_DEFAULT_REGION', 'us-east-1'));
        $this->bucket    = (string) ($config['bucket'] ?? env('AWS_BUCKET', ''));
        $this->endpoint  = !empty($config['endpoint']) ? rtrim((string) $config['endpoint'], '/') : null;
        $this->urlCustom = !empty($config['url']) ? rtrim((string) $config['url'], '/') : null;
    }

    public function put(string $path, mixed $contents, array $options = []): bool
    {
        $body = is_resource($contents) ? (string) stream_get_contents($contents) : (string) $contents;
        $contentType = (string) ($options['mimetype'] ?? $options['ContentType'] ?? 'application/octet-stream');

        $headers = [
            'Content-Type' => $contentType,
        ];

        $response = $this->sendRequest('PUT', $path, $body, $headers);
        return $response['status'] >= 200 && $response['status'] < 300;
    }

    public function get(string $path): ?string
    {
        $response = $this->sendRequest('GET', $path);
        return $response['status'] === 200 ? $response['body'] : null;
    }

    public function exists(string $path): bool
    {
        $response = $this->sendRequest('HEAD', $path);
        return $response['status'] === 200;
    }

    public function delete(string|array $paths): bool
    {
        $paths = is_array($paths) ? $paths : [$paths];
        $allSuccess = true;

        foreach ($paths as $path) {
            $response = $this->sendRequest('DELETE', $path);
            $allSuccess = ($response['status'] >= 200 && $response['status'] < 300) && $allSuccess;
        }

        return $allSuccess;
    }

    public function copy(string $from, string $to): bool
    {
        $headers = [
            'x-amz-copy-source' => '/' . $this->bucket . '/' . ltrim($from, '/'),
        ];

        $response = $this->sendRequest('PUT', $to, '', $headers);
        return $response['status'] >= 200 && $response['status'] < 300;
    }

    public function move(string $from, string $to): bool
    {
        return $this->copy($from, $to) && $this->delete($from);
    }

    public function size(string $path): int
    {
        $response = $this->sendRequest('HEAD', $path);
        return (int) ($response['headers']['content-length'] ?? 0);
    }

    public function lastModified(string $path): int
    {
        $response = $this->sendRequest('HEAD', $path);
        $lastMod = $response['headers']['last-modified'] ?? null;
        return $lastMod ? (int) strtotime($lastMod) : 0;
    }

    public function url(string $path): string
    {
        if ($this->urlCustom !== null) {
            return $this->urlCustom . '/' . ltrim($path, '/');
        }

        if ($this->endpoint !== null) {
            return "{$this->endpoint}/{$this->bucket}/" . ltrim($path, '/');
        }

        return "https://{$this->bucket}.s3.{$this->region}.amazonaws.com/" . ltrim($path, '/');
    }

    public function temporaryUrl(string $path, \DateTimeInterface $expiration, array $options = []): string
    {
        $expires = max(1, $expiration->getTimestamp() - time());
        $now = time();
        $dateStamp = gmdate('Ymd', $now);
        $amzDate = gmdate('Ymd\THis\Z', $now);
        $cleanPath = '/' . ltrim($path, '/');

        $host = $this->getHost();
        $service = 's3';
        $credentialScope = "{$dateStamp}/{$this->region}/{$service}/aws4_request";

        $canonicalQuery = [
            'X-Amz-Algorithm'     => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential'    => "{$this->key}/{$credentialScope}",
            'X-Amz-Date'          => $amzDate,
            'X-Amz-Expires'       => (string) $expires,
            'X-Amz-SignedHeaders' => 'host',
        ];

        ksort($canonicalQuery);
        $canonicalQueryString = http_build_query($canonicalQuery, '', '&', PHP_QUERY_RFC3986);

        $canonicalRequest = "GET\n{$cleanPath}\n{$canonicalQueryString}\nhost:{$host}\n\nhost\nUNSIGNED-PAYLOAD";
        $stringToSign = "AWS4-HMAC-SHA256\n{$amzDate}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);

        $kDate    = hash_hmac('sha256', $dateStamp, 'AWS4' . $this->secret, true);
        $kRegion  = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $urlBase = $this->url('');
        return "{$urlBase}{$cleanPath}?{$canonicalQueryString}&X-Amz-Signature={$signature}";
    }

    public function files(string $directory = '', bool $recursive = false): array
    {
        $prefix = ltrim($directory, '/');
        if ($prefix !== '' && !str_ends_with($prefix, '/')) {
            $prefix .= '/';
        }

        $params = $prefix !== '' ? '?prefix=' . urlencode($prefix) : '';
        $response = $this->sendRequest('GET', $params);

        if ($response['status'] !== 200 || empty($response['body'])) {
            return [];
        }

        $files = [];
        if (preg_match_all('#<Key>(.*?)</Key>#', $response['body'], $matches)) {
            $files = $matches[1];
        }

        return $files;
    }

    public function makeDirectory(string $path): bool
    {
        return true;
    }

    public function deleteDirectory(string $path): bool
    {
        $files = $this->files($path, true);
        return $this->delete($files);
    }

    private function getHost(): string
    {
        if ($this->endpoint !== null) {
            return parse_url($this->endpoint, PHP_URL_HOST) ?? 'localhost';
        }

        return "{$this->bucket}.s3.{$this->region}.amazonaws.com";
    }

    /**
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    private function sendRequest(string $method, string $path, string $body = '', array $headers = []): array
    {
        if ($this->key === '' || $this->secret === '') {
            Log::warning('S3 request skipped: missing credentials.');
            return ['status' => 500, 'headers' => [], 'body' => ''];
        }

        $cleanPath = '/' . ltrim($path, '/');
        $host = $this->getHost();
        $now = time();
        $dateStamp = gmdate('Ymd', $now);
        $amzDate = gmdate('Ymd\THis\Z', $now);

        $payloadHash = hash('sha256', $body);
        $headers['host'] = $host;
        $headers['x-amz-date'] = $amzDate;
        $headers['x-amz-content-sha256'] = $payloadHash;

        ksort($headers);
        $canonicalHeaders = '';
        $signedHeadersList = [];

        foreach ($headers as $k => $v) {
            $lowerKey = strtolower((string) $k);
            $canonicalHeaders .= "{$lowerKey}:{$v}\n";
            $signedHeadersList[] = $lowerKey;
        }

        $signedHeaders = implode(';', $signedHeadersList);
        $canonicalRequest = "{$method}\n{$cleanPath}\n\n{$canonicalHeaders}\n{$signedHeaders}\n{$payloadHash}";

        $service = 's3';
        $credentialScope = "{$dateStamp}/{$this->region}/{$service}/aws4_request";
        $stringToSign = "AWS4-HMAC-SHA256\n{$amzDate}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);

        $kDate    = hash_hmac('sha256', $dateStamp, 'AWS4' . $this->secret, true);
        $kRegion  = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $authHeader = "AWS4-HMAC-SHA256 Credential={$this->key}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}";
        $headers['Authorization'] = $authHeader;

        $url = ($this->endpoint !== null ? $this->endpoint . '/' . $this->bucket : "https://{$host}") . $cleanPath;

        $httpHeaders = [];
        foreach ($headers as $k => $v) {
            $httpHeaders[] = "{$k}: {$v}";
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $httpHeaders,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HEADER         => true,
        ]);

        if ($method === 'PUT' || $method === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $rawResponse = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $respHeaders = [];
        $respBody = '';

        if ($rawResponse !== false && is_string($rawResponse)) {
            $headerStr = substr($rawResponse, 0, $headerSize);
            $respBody = substr($rawResponse, $headerSize);

            foreach (explode("\r\n", $headerStr) as $line) {
                if (str_contains($line, ':')) {
                    [$k, $v] = explode(':', $line, 2);
                    $respHeaders[strtolower(trim($k))] = trim($v);
                }
            }
        }

        return [
            'status'  => $httpCode,
            'headers' => $respHeaders,
            'body'    => $respBody,
        ];
    }
}
