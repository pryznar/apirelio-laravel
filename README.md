# Apirelio Laravel SDK

Fail-safe request analytics for Laravel APIs. The SDK records normalized route
metrics and customer integration context without capturing request or response
bodies, credentials, cookies, query strings, IP addresses, or personal data.
The shared event contract, privacy rules and delivery primitives come from
`apirelio/php-core`, installed automatically by Composer.

## Requirements

- PHP 8.2, 8.3 or 8.4
- Laravel 11 or 12

## Installation

```bash
composer require apirelio/laravel:^0.2
php artisan vendor:publish --tag=apirelio-config
```

Configure the project key shown once in the Apirelio dashboard:

```dotenv
APIRELIO_ENABLED=true
APIRELIO_ENDPOINT=https://api.apirelio.com
APIRELIO_API_KEY=apr_live_xxxxxxxxx
APIRELIO_SERVICE=billing-api
APIRELIO_ENVIRONMENT=production
APIRELIO_RELEASE=2026.07.29.1
APIRELIO_TRANSPORT=queue
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
Route::middleware(['auth:sanctum', 'apirelio'])->group(function (): void {
    Route::post('/invoices/{invoice}/send', SendInvoiceController::class);
});
```

Or use the class directly:

```php
use Apirelio\Laravel\Middleware\TrackApiRequest;

Route::middleware(TrackApiRequest::class)->group(/* ... */);
```

Only paths allowed by `apirelio.paths` are recorded. Laravel route templates are
used instead of concrete URLs, so `/invoices/123` becomes
`/invoices/{invoice}` and query strings are never sent.

## Customer and application identity

Register resolvers in an application service provider:

```php
use Illuminate\Http\Request;
use Apirelio\Laravel\Data\ApirelioApplication;
use Apirelio\Laravel\Data\ApirelioCustomer;
use Apirelio\Laravel\Facades\Apirelio;

Apirelio::resolveCustomerUsing(
    static function (Request $request): ?ApirelioCustomer {
        $client = $request->user();

        return $client === null
            ? null
            : new ApirelioCustomer(
                id: (string) $client->company_id,
                name: $client->company->name,
                plan: $client->company->plan,
            );
    },
);

Apirelio::resolveApplicationUsing(
    static fn (Request $request): ?ApirelioApplication =>
        $request->user()?->api_client_id === null
            ? null
            : new ApirelioApplication(
                id: (string) $request->user()->api_client_id,
                name: 'ERP connector',
            ),
);
```

## Error codes and metadata

JSON error codes are read from `error.code` by default. Override a code during
the current request when needed:

```php
Apirelio::setErrorCode('INVALID_CURRENCY');
```

Custom metadata is allow-listed in `config/apirelio.php`:

```php
'metadata_keys' => ['integration_type', 'region'],
```

```php
Apirelio::addMetadata([
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
APIRELIO_BATCH_SIZE=500
APIRELIO_FLUSH_INTERVAL_SECONDS=10
APIRELIO_TIMEOUT_SECONDS=2
APIRELIO_CONNECT_TIMEOUT_SECONDS=0.5
```

Transport and resolver failures are caught by the SDK. Analytics may lose an
event, but it never changes the customer API response or masks its exception.
