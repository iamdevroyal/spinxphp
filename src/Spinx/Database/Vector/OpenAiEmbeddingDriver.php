<?php

declare(strict_types=1);

namespace Spinx\Database\Vector;

use Spinx\Support\Config;

/**
 * Generates vector embeddings via OpenAI / OpenAI-compatible API endpoints (e.g. text-embedding-3-small, Ollama).
 */
final class OpenAiEmbeddingDriver implements EmbeddingDriverInterface
{
    private string $apiKey;
    private string $model;
    private string $baseUrl;
    private int $dimensions;

    public function __construct(?array $config = null)
    {
        $cfg = $config ?? (array) Config::get('vector.drivers.openai', []);

        $this->apiKey     = (string) ($cfg['api_key'] ?? env('OPENAI_API_KEY', ''));
        $this->model      = (string) ($cfg['model'] ?? env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'));
        $this->baseUrl    = rtrim((string) ($cfg['base_url'] ?? env('OPENAI_BASE_URL', 'https://api.openai.com/v1')), '/');
        $this->dimensions = (int) ($cfg['dimensions'] ?? env('VECTOR_DIMENSIONS', 1536));
    }

    public function embed(string $text): array
    {
        $batch = $this->embedBatch([$text]);
        return $batch[0] ?? [];
    }

    public function embedBatch(array $texts): array
    {
        if ($this->apiKey === '' && !str_contains($this->baseUrl, 'localhost') && !str_contains($this->baseUrl, '127.0.0.1')) {
            throw new \RuntimeException('OpenAI API key is missing for Vector embeddings.');
        }

        $url = "{$this->baseUrl}/embeddings";
        $payload = json_encode([
            'model' => $this->model,
            'input' => array_values($texts),
        ], JSON_THROW_ON_ERROR);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode >= 400) {
            throw new \RuntimeException("Embedding request failed [HTTP {$httpCode}]: {$error} {$response}");
        }

        $decoded = json_decode((string) $response, true, 512, JSON_THROW_ON_ERROR);
        $data = $decoded['data'] ?? [];

        $embeddings = [];
        foreach ($data as $item) {
            $embeddings[] = $item['embedding'] ?? [];
        }

        return $embeddings;
    }

    public function dimensions(): int
    {
        return $this->dimensions;
    }
}
