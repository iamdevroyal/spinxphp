<?php

declare(strict_types=1);

namespace Spinx\Container\Attribute;

/**
 * Explicit marker for a request-scoped service. This is currently a no-op
 * since request-scoped is the default for every module-registered service
 * (see Singleton.php for the actual opt-out mechanism) — but attaching it
 * documents intent directly on the class, which matters more here than in
 * a typical framework: on a persistent-process runtime, "is this safe to
 * reuse across requests?" is a question every service author should have
 * to consciously answer, not assume.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class RequestScoped
{
}
