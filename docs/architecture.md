# Architecture

## The enforced module system

Every piece of application code lives under `app/Modules/<Name>/`, split
into three layers:

```
app/Modules/Orders/
├── Domain/
│   ├── Entities/          Plain PHP objects — zero persistence/HTTP awareness
│   ├── ValueObjects/
│   ├── Events/
│   └── Repositories/      Interfaces only
├── Application/
│   ├── Services/          Orchestrates Domain logic
│   ├── Commands/
│   └── Queries/
├── Infrastructure/
│   ├── Repositories/      Concrete implementations of Domain interfaces
│   ├── Http/
│   │   ├── Controllers/
│   │   ├── Middleware/
│   │   └── Views/         Module-owned .spinx.html templates
│   └── Persistence/
│       ├── Models/        ORM active-record models (Spinx\Database\Model)
│       └── Migrations/
└── module.php              Routes + DI bindings — the ONLY entry point
```

**This isn't a convention — it's structurally enforced.** The kernel's
route/service loader (`Spinx\Routing\ModuleLoader`) is the *only* code
path that registers a route or a service, and it only ever reads
`module.php`. There is no fallback to a bare `app/Controllers/` — a
controller that isn't wired through a module's `module.php` is simply
invisible to the framework. See `ModuleLoader`'s own docblock for the
reasoning.

### Why the Model/Entity split

`Infrastructure/Persistence/Models/Order.php` (extends `Spinx\Database\Model`)
and `Domain/Entities/Order.php` (plain PHP) look similar but serve
different purposes. The Model knows about tables, columns, and casts —
it's an Infrastructure detail. The Entity should know about business
rules and nothing else. A repository in `Infrastructure/Repositories/`
translates between them:

```php
final class OrderRepository implements OrderRepositoryInterface
{
    public function find(int|string $id): ?Order // Domain entity, not the Model
    {
        $model = OrderModel::find($id);
        return $model === null ? null : new Order(/* map fields */);
    }
}
```

For simple modules (see `Health`, `Todo`) this indirection is often
skipped — controllers call the Model directly. That's a legitimate
choice when there's no real domain logic to protect; `make:repository`
exists for when there is.

## Request lifecycle

1. `Spinx\Kernel\Kernel::boot()` runs **once per process** (not per
   request) — compiles the DI container, compiles routes, wires
   `Model::setConnectionManager()`.
2. Each request: `RequestScope::reset()` runs first — see "State safety"
   below — then the route matches, the controller resolves through the
   container (autowired), and its return value must be a Symfony
   `Response`.

## State safety on a persistent-process runtime

Both RoadRunner and Swoole keep the PHP process alive across many
requests — nothing resets automatically the way it does under
traditional PHP-FPM. Two mechanisms handle this:

**Request-scoped services (automatic).** Every service a module
registers in `module.php` is request-scoped by default — torn down and
rebuilt at the start of every request. This is driven by
`Spinx\Container\Compiler\RequestScopePass`, which auto-tags every
service a module's `services` closure registers (no manual tagging
needed) and classifies it as request-scoped unless the class carries
`#[Singleton]`:

```php
use Spinx\Container\Attribute\Singleton;

#[Singleton]
final class PriceFormatter
{
    // Stateless — safe to reuse across requests.
}
```

**Static properties (your responsibility, with a safety net).** Neither
mechanism above touches static properties — they live on the class
itself, not a container-managed instance. A custom PHPStan rule
(`Spinx\Analysis\NoMutableStaticStateRule`, wired in `phpstan.neon`)
flags any non-`readonly` static property inside `app/Modules`:

```bash
vendor/bin/phpstan analyse
```

See [Runtime Drivers](runtime-drivers.md) for the additional rules that
apply specifically to database connections under Swoole's coroutine
model.
