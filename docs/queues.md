# Asynchronous Queues & Worker Daemons

Spinx features a universal, production-grade queue subsystem designed to handle high-throughput background workloads without blocking persistent HTTP workers.

---

## 🚀 Quick Usage

```php
use Spinx\Queue\Queue;
use App\Modules\Billing\Application\Jobs\ProcessInvoiceJob;

// 1. Push to default queue
Queue::push(new ProcessInvoiceJob($invoiceId));

// 2. Push with custom priority to a named queue
Queue::onQueue('billing')
    ->withPriority(10)
    ->push(new ProcessInvoiceJob($invoiceId));

// 3. Delay job execution by 60 seconds
Queue::later(60, new ProcessInvoiceJob($invoiceId));
```

---

## 🛠️ Defining Queueable Jobs

Every job lives in `app/Modules/<ModuleName>/Application/Jobs/` and implements the `Spinx\Queue\Job` interface:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Jobs;

use App\Modules\Billing\Domain\Repositories\InvoiceRepositoryInterface;
use Spinx\Broadcasting\Broadcast;
use Spinx\Queue\Job;

final class ProcessInvoiceJob implements Job
{
    /**
     * Keep constructor arguments to lightweight serializable primitives.
     */
    public function __construct(
        public readonly int $invoiceId,
    ) {
    }

    public function handle(): void
    {
        // Resolve application dependencies cleanly via DI container
        $invoices = \Spinx\Kernel\Kernel::getContainer()->get(InvoiceRepositoryInterface::class);
        $invoice = $invoices->findById($this->invoiceId);

        if ($invoice === null) {
            return;
        }

        $invoice->markAsProcessed();
        $invoices->save($invoice);

        // Notify client in real-time
        Broadcast::private('invoices.' . $this->invoiceId)->event('InvoiceProcessed', [
            'id' => $this->invoiceId,
            'status' => 'processed',
        ]);
    }
}
```

---

## ⚙️ Queue Drivers & Configuration

Queue settings are managed in `config/queue.php` and `.env`:

```php
return [
    'default' => env('QUEUE_CONNECTION', 'database'),

    'connections' => [
        'sync' => [
            'driver' => 'sync', // Executes immediately in-process (ideal for local testing)
        ],
        'database' => [
            'driver'     => 'database',
            'table'      => 'spinx_jobs',
            'queue'      => 'default',
            'retry_after'=> 300, // 5 min reservation timeout
        ],
        'redis' => [
            'driver'     => 'redis',
            'connection' => 'queue',
            'queue'      => 'default',
            'retry_after'=> 300,
        ],
    ],
];
```

### Database Migration for Queues
Spinx stores queued and failed jobs in dedicated database tables:
- `spinx_jobs`: `id`, `job_ref` (UUID), `queue`, `payload`, `priority`, `attempts`, `reserved_at`, `available_at`.
- `spinx_failed_jobs`: `id`, `job_ref`, `queue`, `payload`, `exception`, `failed_at`.

---

## 🔒 Cryptographic HMAC Tampering Defense

To defend against **PHP Object Injection & Remote Code Execution (RCE)** attacks, Spinx cryptographically signs every serialized queue payload with **HMAC-SHA256** using the application's secret `APP_KEY`. 

When a job is popped by the worker daemon, the signature is verified before calling `unserialize()`. Tampered or forged payloads are rejected and logged automatically.

---

## 🔄 Running Queue Worker Daemons

Run the high-performance queue worker CLI command:

```bash
# Process default queue
php spinx queue:work

# Process multiple queues in priority order (high priority first)
php spinx queue:work --queue=high,billing,default --sleep=2 --max-jobs=1000

# Process Redis queue
php spinx queue:work --driver=redis
```

### Supervisor Configuration (Production)
For production deployments, supervise `spinx queue:work` using systemd, Docker, or Supervisord:

```ini
[program:spinx-worker]
command=php /var/www/spinx queue:work --queue=high,default
process_name=%(program_name)s_%(process_num)02d
numprocs=4
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/storage/logs/worker.log
```
