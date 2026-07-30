<?php

declare(strict_types=1);

namespace Apirelio\Laravel\Transport;

use Apirelio\Core\Config\TransportConfig;
use Apirelio\Laravel\Contracts\EventTransport;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Client\Factory;

final class HttpBatchTransport extends \Apirelio\Core\Transport\HttpBatchTransport implements EventTransport
{
    public function __construct(
        Factory $http,
        Repository $config,
    ) {
        parent::__construct(
            new LaravelIngestionClient($http),
            new TransportConfig(
                endpoint: (string) $config->get('apirelio.endpoint', 'https://api.apirelio.com'),
                apiKey: (string) $config->get('apirelio.api_key', ''),
                timeoutSeconds: (float) $config->get('apirelio.timeout_seconds', 2),
                connectTimeoutSeconds: (float) $config->get('apirelio.connect_timeout_seconds', 0.5),
            ),
        );
    }
}
