# Lalamove Webhooks

## How it works

Lalamove uses webhooks to push order status changes to your application in real time. The flow is:

1. You create an order and pass a `metadata` object containing your internal order number (e.g. `referenceId`).
2. Whenever the order status changes (driver assigned, picked up, delivered, cancelled, etc.), Lalamove sends a `POST` request to the webhook URL you registered in the partner portal.
3. Your endpoint receives the payload, looks up the order via the metadata, and updates your system.

Your app does **not** need to poll the Lalamove API for status updates — Lalamove pushes them to you automatically.

## Registering the webhook URL

Go to **https://partnerportal.lalamove.com/developers/webhooks** and enter your publicly reachable HTTPS endpoint, e.g.:

```
https://yourdomain.com/webhooks/lalamove
```

## Incoming payload shape

```json
{
  "eventType": "ORDER_STATUS_CHANGED",
  "orderId": "ord-abc123",
  "status": "PICKED_UP",
  "metadata": { "referenceId": "YOUR-INTERNAL-ORDER-NUMBER" },
  "updatedAt": "2024-06-01T10:00:00.000Z"
}
```

| Field | Description |
|---|---|
| `orderId` | Lalamove's own order ID |
| `status` | New Lalamove status string (see status map below) |
| `metadata.referenceId` | Your internal order number, passed at order creation |
| `updatedAt` | ISO 8601 timestamp of the status change |

The metadata key name is configurable via `LALAMOVE_ORDER_NUMBER_KEY` in `.env` (defaults to `referenceId`). If the key is missing from metadata, the driver falls back to `orderId` as the order number.

## Handling the webhook in Laravel

**Route** (`routes/api.php` or `routes/web.php`):
```php
Route::post('/webhooks/lalamove', [ShipmentWebhookController::class, 'lalamove']);
```

**Controller**:
```php
use Nextbyte\Courier\Facades\Courier;
use Nextbyte\Courier\ShipmentStatusPush;
use Illuminate\Http\Request;

class ShipmentWebhookController extends Controller
{
    public function lalamove(Request $request)
    {
        return Courier::vendor('lalamove')->pushShipmentStatus(
            function (ShipmentStatusPush $push) {
                $order = Order::where('number', $push->getOrderNumber())->firstOrFail();
                $order->update(['shipment_status' => $push->getStatus()]);
                return $order;
            },
            $request->all()
        );
    }
}
```

`pushShipmentStatus` always returns HTTP 200 — `{"success": true}` on success, `{"success": false, "message": "..."}` on error — because Lalamove expects a 200 to confirm receipt regardless of outcome.

## CSRF exemption

Lalamove's POST will be rejected by Laravel's CSRF middleware. Exclude the route in `app/Http/Middleware/VerifyCsrfToken.php`:

```php
protected $except = [
    'webhooks/lalamove',
];
```

## Status map

| Lalamove status | `ShipmentStatus` constant |
|---|---|
| `ASSIGNING_DRIVER` | `Pending` |
| `ON_GOING` | `Accepted` |
| `PICKED_UP` | `Pickup` |
| `COMPLETED` | `Delivered` |
| `CANCELED` | `ReturnStart` |
| `REJECTED` | `DeliveryAttempted` |
| `EXPIRED` | `OnHold` |
| (anything else) | `Unknown` |

## Local development with ngrok

`localhost` is not reachable by Lalamove. Use ngrok to expose your local Laravel app over a public HTTPS URL.

### 1. Install ngrok

```bash
snap install ngrok
```

Or download directly from https://ngrok.com/download.

### 2. Create a free account and connect your authtoken

Sign up at https://ngrok.com, then grab your authtoken from https://dashboard.ngrok.com/get-started/your-authtoken.

```bash
ngrok config add-authtoken YOUR_AUTH_TOKEN_HERE
```

Only needed once — saved to `~/.config/ngrok/ngrok.yml`.

### 3. Start your Laravel app

```bash
php artisan serve --port=8000
```

### 4. Start the ngrok tunnel

In a separate terminal:

```bash
ngrok http 8000
```

You will see output like:

```
Forwarding   https://abc123.ngrok-free.app -> http://localhost:8000
```

### 5. Set up the webhook route in your Laravel app

Add to `routes/api.php` or `routes/web.php`:

```php
Route::post('/webhooks/lalamove', [ShipmentWebhookController::class, 'lalamove']);
```

Exclude it from CSRF in `app/Http/Middleware/VerifyCsrfToken.php`:

```php
protected $except = [
    'webhooks/lalamove',
];
```

> Lalamove validates your URL before registering it by sending a request and expecting a `200` response. The route **must exist and be reachable** before you register, or the registration will fail with a `422` error.

### 6. Register the webhook URL

Update `LALAMOVE_WEBHOOK_URL` in your `.env` with the full path including the route:

```
LALAMOVE_WEBHOOK_URL=https://abc123.ngrok-free.app/webhooks/lalamove
```

Then register it via the API test:

```bash
./vendor/bin/phpunit tests/Unit/LalamoveApiTest.php --filter test_it_can_register_webhook_url
```

Or register it manually in the partner portal at https://partnerportal.lalamove.com/developers/webhooks.

### 7. Inspect incoming payloads

ngrok provides a local web UI at http://localhost:4040 where you can see every request Lalamove sends to your webhook, including the full payload. Useful for debugging.

### Notes

- The ngrok URL **changes every restart** on the free plan — re-register with Lalamove each time. A paid plan gives you a fixed domain.
- Keep both terminals open (Laravel app + ngrok). If either stops, webhooks will not reach your app.
- Swap the ngrok URL for your production URL when deploying.
