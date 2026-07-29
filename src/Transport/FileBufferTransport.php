<?php

declare(strict_types=1);

namespace Tracium\Laravel\Transport;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Tracium\Core\Config\BufferConfig;
use Tracium\Laravel\Contracts\EventTransport;

final class FileBufferTransport extends \Tracium\Core\Transport\FileBufferTransport implements EventTransport
{
    public function __construct(
        HttpBatchTransport $http,
        Repository $config,
        Container $container,
    ) {
        $configured = $config->get('tracium.buffer_path');
        $storagePath = $container->bound('path.storage')
            ? (string) $container->make('path.storage')
            : sys_get_temp_dir();
        $path = is_string($configured) && $configured !== ''
            ? $configured
            : rtrim($storagePath, '/').'/framework/cache/tracium/events.ndjson';

        parent::__construct(
            $http,
            new BufferConfig(
                path: $path,
                batchSize: (int) $config->get('tracium.batch.size', 500),
                flushIntervalSeconds: (int) $config->get('tracium.batch.flush_interval_seconds', 10),
            ),
        );
    }
}
