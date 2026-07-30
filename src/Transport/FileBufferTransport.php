<?php

declare(strict_types=1);

namespace Apirelio\Laravel\Transport;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Apirelio\Core\Config\BufferConfig;
use Apirelio\Laravel\Contracts\EventTransport;

final class FileBufferTransport extends \Apirelio\Core\Transport\FileBufferTransport implements EventTransport
{
    public function __construct(
        HttpBatchTransport $http,
        Repository $config,
        Container $container,
    ) {
        $configured = $config->get('apirelio.buffer_path');
        $storagePath = $container->bound('path.storage')
            ? (string) $container->make('path.storage')
            : sys_get_temp_dir();
        $path = is_string($configured) && $configured !== ''
            ? $configured
            : rtrim($storagePath, '/').'/framework/cache/apirelio/events.ndjson';

        parent::__construct(
            $http,
            new BufferConfig(
                path: $path,
                batchSize: (int) $config->get('apirelio.batch.size', 500),
                flushIntervalSeconds: (int) $config->get('apirelio.batch.flush_interval_seconds', 10),
            ),
        );
    }
}
