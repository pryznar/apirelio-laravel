<?php

declare(strict_types=1);

namespace Tracium\Laravel\Transport;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use JsonException;
use RuntimeException;
use Tracium\Laravel\Contracts\EventTransport;

final readonly class FileBufferTransport implements EventTransport
{
    public function __construct(
        private HttpBatchTransport $http,
        private Repository $config,
        private Container $container,
    ) {}

    /** @throws JsonException */
    public function send(array $events): void
    {
        if ($events === []) {
            return;
        }

        $path = $this->path();
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create the Tracium buffer directory.');
        }

        $lines = array_map(
            static fn (array $event): string => json_encode($event, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            $events,
        );

        if (file_put_contents($path, implode("\n", $lines)."\n", FILE_APPEND | LOCK_EX) === false) {
            throw new RuntimeException('Unable to write the Tracium event buffer.');
        }

        $this->flushIfDue();
    }

    public function flushIfDue(bool $force = false): void
    {
        $path = $this->path();
        if (! is_file($path)) {
            return;
        }

        $size = max(1, (int) $this->config->get('tracium.batch.size', 500));
        $interval = max(1, (int) $this->config->get('tracium.batch.flush_interval_seconds', 10));
        $lineCount = $this->lineCount($path);
        $modifiedAt = filemtime($path) ?: time();

        if (! $force && $lineCount < $size && (time() - $modifiedAt) < $interval) {
            return;
        }

        $this->flush($size);
    }

    private function flush(int $size): void
    {
        $path = $this->path();
        $handle = fopen($path, 'c+');
        if ($handle === false || ! flock($handle, LOCK_EX)) {
            throw new RuntimeException('Unable to lock the Tracium event buffer.');
        }

        try {
            rewind($handle);
            $lines = [];
            while (($line = fgets($handle)) !== false) {
                if (trim($line) !== '') {
                    $lines[] = $line;
                }
            }

            $batchLines = array_slice($lines, 0, $size);
            if ($batchLines === []) {
                return;
            }

            $events = array_map(
                static fn (string $line): array => (array) json_decode($line, true, 512, JSON_THROW_ON_ERROR),
                $batchLines,
            );

            $this->http->send($events);

            $remaining = array_slice($lines, count($batchLines));
            ftruncate($handle, 0);
            rewind($handle);
            if ($remaining !== []) {
                fwrite($handle, implode('', $remaining));
            }
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function lineCount(string $path): int
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return 0;
        }

        $count = 0;
        while (fgets($handle) !== false) {
            $count++;
        }
        fclose($handle);

        return $count;
    }

    private function path(): string
    {
        $configured = $this->config->get('tracium.buffer_path');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $storagePath = $this->container->bound('path.storage')
            ? (string) $this->container->make('path.storage')
            : sys_get_temp_dir();

        return rtrim($storagePath, '/').'/framework/cache/tracium/events.ndjson';
    }
}
