<?php

declare(strict_types=1);

namespace Spinx\Auth\Jwt;

/**
 * JwtException — Thrown for any JWT validation failure:
 * malformed token, bad signature, expired, not-yet-valid, or decode errors.
 */
final class JwtException extends \RuntimeException
{
}
