<?php

declare(strict_types=1);

namespace Spinx\Database\Pagination;

/**
 * Cursor — Encodes and decodes base64 URL-safe cursor tokens for cursor pagination.
 */
final class Cursor
{
    public function __construct(
        public readonly int|string $value,
        public readonly string $column = 'id',
        public readonly string $direction = 'asc',
    ) {
    }

    /**
     * Encode cursor parameters into a URL-safe base64 token.
     */
    public function encode(): string
    {
        $json = json_encode([
            '_c' => $this->column,
            '_v' => $this->value,
            '_d' => strtolower($this->direction),
        ], JSON_THROW_ON_ERROR);

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    /**
     * Decode a base64 cursor token back into a Cursor instance.
     */
    public static function decode(?string $token): ?self
    {
        if ($token === null || trim($token) === '') {
            return null;
        }

        $padded = str_pad(
            strtr($token, '-_', '+/'),
            strlen($token) % 4 === 0 ? strlen($token) : strlen($token) + (4 - strlen($token) % 4),
            '='
        );

        $json = base64_decode($padded, strict: true);

        if ($json === false) {
            return null;
        }

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!isset($data['_v'])) {
            return null;
        }

        return new self(
            value: $data['_v'],
            column: (string) ($data['_c'] ?? 'id'),
            direction: (string) ($data['_d'] ?? 'asc'),
        );
    }
}
