<?php

declare(strict_types=1);

namespace Tracium\Laravel\Transport;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Client\Factory;
use RuntimeException;
use Tracium\Laravel\Contracts\EventTransport;

final readonly class HttpBatchTransport implements EventTransport
{
    public function __construct(
        private Factory $http,
        private Repository $config,
    ) {}

    public function send(array $events): void
    {
        if ($events === []) {
            return;
        }

        $endpoint = rtrim((string) $this->config->get('tracium.endpoint'), '/').'/ingest/v1/events/batch';
        $apiKey = (string) $this->config->get('tracium.api_key');

        if ($apiKey === '') {
            throw new RuntimeException('TRACIUM_API_KEY is not configured.');
        }

        $this->http
            ->withToken($apiKey)
            ->acceptJson()
            ->asJson()
            ->connectTimeout((float) $this->config->get('tracium.connect_timeout_seconds', 0.5))
            ->timeout((float) $this->config->get('tracium.timeout_seconds', 2))
            ->retry(2, 100)
            ->post($endpoint, ['events' => $events])
            ->throw();
    }
}
