<?php

declare(strict_types=1);

namespace Apirelio\Laravel\Transport;

use Apirelio\Core\Contracts\IngestionClient;
use Illuminate\Http\Client\Factory;

final readonly class LaravelIngestionClient implements IngestionClient
{
    public function __construct(private Factory $http) {}

    public function postBatch(
        string $endpoint,
        string $apiKey,
        array $events,
        float $timeoutSeconds,
        float $connectTimeoutSeconds,
    ): void {
        $this->http
            ->withToken($apiKey)
            ->acceptJson()
            ->asJson()
            ->connectTimeout($connectTimeoutSeconds)
            ->timeout($timeoutSeconds)
            ->post($endpoint, ['events' => $events])
            ->throw();
    }
}
