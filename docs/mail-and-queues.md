# Mail, Queues & Task Scheduler

## Mail & Mailable Generation

```bash
php spinx make:mail Orders OrderShipped
```

Generates three files:
- `Application/Mail/OrderShippedMailable.php` — extends `Spinx\Mail\Mailable`, configures subject/view/recipient in `build()`
- `Application/Jobs/SendOrderShippedJob.php` — implements `Spinx\Queue\Job`, sends the Mailable asynchronously via the queue
- `Infrastructure/Http/Views/mail/order_shipped.spinx.html` — a view template with `{{ }}`, `@if`, and `@foreach`

### Sending Mail

```php
use App\Modules\Orders\Application\Jobs\SendOrderShippedJob;
use Spinx\Queue\QueueManager;

final class OrderShipController
{
    public function __construct(private readonly QueueManager $queue) {}

    public function __invoke(Request $request, string $id): Response
    {
        $this->queue->dispatch(new SendOrderShippedJob($customerEmail));

        return new Response('Shipped');
    }
}
```

## Background Job Queues

Run a background worker process:
```bash
php spinx queue:work
```

For immediate in-process execution (e.g. in tests):
```php
$queue->dispatchSync($job);
```

### Job Context & Serialization

Jobs are serialized into the database queue table. Inside `handle()`, resolve services fresh via `JobContext`:

```php
public function handle(): void
{
    $mailer = \Spinx\Queue\JobContext::resolve(Mailer::class);
    $mailer->send(new SomeMailable($this->recipientEmail));
}
```

## The Task Scheduler (`schedule.php`)

Spinx includes an in-framework cron scheduler. Define scheduled tasks in `schedule.php` at the project root:

```php
use Spinx\Schedule\Scheduler;

return function (Scheduler $scheduler, $container): void {
    // Daily cleanup at 03:00:
    $scheduler->call(function () use ($container) {
        $container->get(QueueManager::class)->dispatch(new CleanupJob());
    }, 'daily cleanup')->daily('03:00');

    // Every 15 minutes:
    $scheduler->call(fn() => syncData(), 'sync data')->everyMinutes(15);

    // Every Monday at 08:30:
    $scheduler->call(fn() => sendWeeklyReport(), 'weekly report')->weekly(1, '08:30');
};
```

### Fluent Frequency Methods
- `->everyMinute()`: Runs every minute (`* * * * *`).
- `->everyMinutes(int $n)`: Runs every `n` minutes (`*/n * * * *`).
- `->hourly()`: Runs at minute 0 of every hour (`0 * * * *`).
- `->daily(string $time = '00:00')`: Runs daily at `H:i`.
- `->weekly(int $weekday = 1, string $time = '00:00')`: Runs weekly (0 = Sunday, 1 = Monday).
- `->monthly(int $day = 1, string $time = '00:00')`: Runs monthly on day `1..31`.
- `->cron(string $expression)`: Custom 5-field cron expression.

### Running Due Tasks
Add a single OS cron entry to invoke the runner every minute:

```bash
* * * * * cd /path/to/app && php spinx schedule:run >> /dev/null 2>&1
```
