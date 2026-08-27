# Real-Time Event Broadcasting (WebSockets)

Spinx provides a universal event broadcasting subsystem that seamlessly streams server-side events over WebSockets to client applications (browsers, mobile apps, reactive islands).

---

## ⚡ Key Highlights
- **100% Pusher Protocol Compatible:** Works out-of-the-box with **Soketi** (Docker, standalone binary, or npm), **Pusher Cloud**, and **Laravel Reverb**.
- **No Heavy External SDKs:** Built with native, high-performance cURL and HMAC-SHA256 signature generation.
- **Built-in Channel Auth:** Secure `POST /_spinx/broadcasting/auth` route with pattern-matched authorization callbacks.
- **Dev-Friendly:** Includes `LogDriver` (prints events to `storage/logs`) and `NullDriver` for zero-setup testing.

---

## 🚀 Quick Usage

### 1. Direct Broadcasting
```php
use Spinx\Broadcasting\Broadcast;

// Public channel
Broadcast::channel('orders')->event('OrderPlaced', ['order_id' => 1234, 'amount' => 49.99]);

// Private channel (requires authentication)
Broadcast::private('user.42')->event('NotificationReceived', ['message' => 'Your subscription is active!']);

// Presence channel (track online users)
Broadcast::presence('chat.room.5')->event('UserJoined', ['id' => 42, 'name' => 'Alice']);
```

### 2. Event-Driven Broadcasting (`ShouldBroadcast`)
```php
namespace App\Modules\Billing\Domain\Events;

use Spinx\Broadcasting\PrivateChannel;
use Spinx\Broadcasting\ShouldBroadcast;

final class InvoiceSettledEvent implements ShouldBroadcast
{
    public function __construct(
        public readonly int $invoiceId,
        public readonly float $amount,
    ) {
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('invoices.' . $this->invoiceId);
    }

    public function broadcastWith(): array
    {
        return [
            'invoice_id' => $this->invoiceId,
            'amount'     => $this->amount,
            'status'     => 'settled',
        ];
    }
}

// Dispatch event anywhere in controllers or application services
\Spinx\Broadcasting\Broadcast::event(new InvoiceSettledEvent(42, 99.00));
```

---

## 🔐 Channel Authorization Callbacks

Register authorization rules for private and presence channels in `app/Modules/<Name>/module.php` or `config/broadcasting.php`:

```php
use Spinx\Broadcasting\Broadcast;

// Exact or wildcard pattern matching
Broadcast::channelAuth('invoices.{id}', function (?object $user, int $invoiceId): bool {
    if ($user === null) {
        return false; // Unauthorized
    }

    // Only allow invoice owner or administrators
    return $user->id === $invoiceId || ($user->is_admin ?? false);
});

// Presence channel returning user data
Broadcast::channelAuth('chat.room.{id}', function (?object $user, int $roomId): array|false {
    if ($user === null) {
        return false;
    }

    return [
        'user_id'   => $user->id,
        'user_info' => ['name' => $user->name, 'avatar' => $user->avatar_url ?? ''],
    ];
});
```

---

## 🌐 Frontend Client Integration (Pusher JS / Echo)

Connect from your Vue, React, or vanilla JavaScript frontend:

```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: 'spinx-app-key',
    wsHost: window.location.hostname,
    wsPort: 6001,
    forceTLS: false,
    disableStats: true,
    authEndpoint: '/_spinx/broadcasting/auth', // Native Spinx channel auth route
});

// Listen on a private channel
window.Echo.private('invoices.42')
    .listen('InvoiceSettledEvent', (e) => {
        console.log('Invoice Settled in Real-Time:', e);
    });
```
