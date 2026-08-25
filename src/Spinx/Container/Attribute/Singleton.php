<?php

declare(strict_types=1);

namespace Spinx\Container\Attribute;

/**
 * Explicit opt-out from Spinx's default request-scoping (build spec §4).
 *
 * Every service a module registers in module.php is request-scoped by
 * default — reset fresh at the start of every request so nothing can leak
 * across requests on a persistent worker/coroutine. Mark a class with this
 * attribute only when it's genuinely safe to persist across requests: it
 * holds no mutable per-request state (a stateless formatter, a read-only
 * config wrapper, a cache client, etc.).
 *
 * Usage:
 *   #[Singleton]
 *   final class PriceFormatter { ... }
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class Singleton
{
}
