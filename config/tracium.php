<?php

declare(strict_types=1);

return [
    'enabled' => env('TRACIUM_ENABLED', true),
    'endpoint' => env('TRACIUM_ENDPOINT', 'https://ingest.tracium.example'),
    'api_key' => env('TRACIUM_API_KEY'),
    'service' => env('TRACIUM_SERVICE', env('APP_NAME', 'laravel')),
    'environment' => env('TRACIUM_ENVIRONMENT', env('APP_ENV', 'production')),
    'release' => env('TRACIUM_RELEASE'),
    'transport' => env('TRACIUM_TRANSPORT', 'queue'),
    'queue' => env('TRACIUM_QUEUE', 'default'),
    'paths' => ['api/*'],
    'timeout_seconds' => (float) env('TRACIUM_TIMEOUT_SECONDS', 2),
    'connect_timeout_seconds' => (float) env('TRACIUM_CONNECT_TIMEOUT_SECONDS', 0.5),
    'batch' => [
        'size' => (int) env('TRACIUM_BATCH_SIZE', 500),
        'flush_interval_seconds' => (int) env('TRACIUM_FLUSH_INTERVAL_SECONDS', 10),
    ],
    'buffer_path' => env('TRACIUM_BUFFER_PATH'),
    'error_code' => [
        'json_path' => env('TRACIUM_ERROR_CODE_JSON_PATH', 'error.code'),
    ],
    'capture_headers' => [
        'x-api-version',
        'x-sdk-version',
        'user-agent',
    ],
    'metadata_keys' => [],
];
