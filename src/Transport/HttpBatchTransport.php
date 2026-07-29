<?php

declare(strict_types=1);

namespace Tracium\Laravel\Transport;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Client\Factory;
use Tracium\Core\Config\TransportConfig;
use Tracium\Laravel\Contracts\EventTransport;

final class HttpBatchTransport extends \Tracium\Core\Transport\HttpBatchTransport implements EventTransport
{
    public function __construct(
        Factory $http,
        Repository $config,
    ) {
        parent::__construct(
            new LaravelIngestionClient($http),
            new TransportConfig(
                endpoint: (string) $config->get('tracium.endpoint', 'https://ingest.tracium.example'),
                apiKey: (string) $config->get('tracium.api_key', ''),
                timeoutSeconds: (float) $config->get('tracium.timeout_seconds', 2),
                connectTimeoutSeconds: (float) $config->get('tracium.connect_timeout_seconds', 0.5),
            ),
        );
    }
}
