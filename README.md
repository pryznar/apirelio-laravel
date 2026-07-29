# Tracium Laravel SDK

Fail-safe request analytics for Laravel APIs. The SDK records normalized route
metrics and customer integration context without capturing request or response
bodies, credentials, cookies, query strings, IP addresses, or personal data.

## Requirements

- PHP 8.2, 8.3 or 8.4
- Laravel 11 or 12

## Installation

```bash
composer require tracium/laravel:^0.1
php artisan vendor:publish --tag=tracium-config
```

Configure the project key shown once in the Tracium dashboard:

```dotenv
TRACIUM_ENABLED=true
TRACIUM_ENDPOINT=https://ingest.tracium.example
TRACIUM_API_KEY=trc_live_xxxxxxxxx
TRACIUM_SERVICE=billing-api
TRACIUM_ENVIRONMENT=production
TRACIUM_RELEASE=2026.07.29.1
TRACIUM_TRANSPORT=queue
```

The production `queue` transport keeps network activity outside customer
requests. Make sure both the Laravel queue worker and scheduler are running:

```bash
php artisan queue:work
php artisan schedule:work
```

## Middleware

Attach the middleware alias to API routes:

```php
Route::middleware(['auth:sanctum', 'tracium'])->group(function (): void {
    Route::post('/invoices/{invoice}/send', SendInvoiceController::class);
});
```

Or use the class directly:

```php
use Tracium\Laravel\Middleware\TrackApiRequest;

Route::middleware(TrackApiRequest::class)->group(/* ... */);
```

Only paths allowed by `tracium.paths` are recorded. Laravel route templates are
used instead of concrete URLs, so `/invoices/123` becomes
`/invoices/{invoice}` and query strings are never sent.

## Customer and application identity

Register resolvers in an application service provider:

```php
use Illuminate\Http\Request;
use Tracium\Laravel\Data\TraciumApplication;
use Tracium\Laravel\Data\TraciumCustomer;
use Tracium\Laravel\Facades\Tracium;

Tracium::resolveCustomerUsing(
    static function (Request $request): ?TraciumCustomer {
        $client = $request->user();

        return $client === null
            ? null
            : new TraciumCustomer(
                id: (string) $client->company_id,
                name: $client->company->name,
                plan: $client->company->plan,
            );
    },
);

Tracium::resolveApplicationUsing(
    static fn (Request $request): ?TraciumApplication =>
        $request->user()?->api_client_id === null
            ? null
            : new TraciumApplication(
                id: (string) $request->user()->api_client_id,
                name: 'ERP connector',
            ),
);
```

## Error codes and metadata

JSON error codes are read from `error.code` by default. Override a code during
the current request when needed:

```php
Tracium::setErrorCode('INVALID_CURRENCY');
```

Custom metadata is allow-listed in `config/tracium.php`:

```php
'metadata_keys' => ['integration_type', 'region'],
```

```php
Tracium::addMetadata([
    'integration_type' => 'accounting',
    'region' => 'eu-central',
]);
```

Values must be scalar. Unlisted keys are discarded.

## Transports and batching

- `queue` (default): enqueue locally, batch in a locked file buffer, and flush
  from the worker/scheduler.
- `sync`: send immediately; intended for local development and diagnostics.
- `file-buffer`: append directly to the local buffer and flush by size or from
  the scheduler.

```dotenv
TRACIUM_BATCH_SIZE=500
TRACIUM_FLUSH_INTERVAL_SECONDS=10
TRACIUM_TIMEOUT_SECONDS=2
TRACIUM_CONNECT_TIMEOUT_SECONDS=0.5
```

Transport and resolver failures are caught by the SDK. Analytics may lose an
event, but it never changes the customer API response or masks its exception.
