<?php

declare(strict_types=1);

return [
    'enabled' => env('APIRELIO_ENABLED', true),
    'endpoint' => env('APIRELIO_ENDPOINT', 'https://api.apirelio.com'),
    'api_key' => env('APIRELIO_API_KEY'),
    'service' => env('APIRELIO_SERVICE', env('APP_NAME', 'laravel')),
    'environment' => env('APIRELIO_ENVIRONMENT', env('APP_ENV', 'production')),
    'release' => env('APIRELIO_RELEASE'),
    'transport' => env('APIRELIO_TRANSPORT', 'queue'),
    'queue' => env('APIRELIO_QUEUE', 'default'),
    'paths' => ['api/*'],
    'timeout_seconds' => (float) env('APIRELIO_TIMEOUT_SECONDS', 2),
    'connect_timeout_seconds' => (float) env('APIRELIO_CONNECT_TIMEOUT_SECONDS', 0.5),
    'batch' => [
        'size' => (int) env('APIRELIO_BATCH_SIZE', 500),
        'flush_interval_seconds' => (int) env('APIRELIO_FLUSH_INTERVAL_SECONDS', 10),
    ],
    'buffer_path' => env('APIRELIO_BUFFER_PATH'),
    'error_code' => [
        'json_path' => env('APIRELIO_ERROR_CODE_JSON_PATH', 'error.code'),
    ],
    'capture_headers' => [
        'x-api-version',
        'x-sdk-version',
        'user-agent',
    ],
    'metadata_keys' => [],
];
