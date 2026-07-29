<?php

declare(strict_types=1);

namespace Tracium\Laravel\Tests;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Http\Client\Factory;
use PHPUnit\Framework\TestCase;
use Tracium\Laravel\Transport\FileBufferTransport;
use Tracium\Laravel\Transport\HttpBatchTransport;

final class FileBufferTransportTest extends TestCase
{
    private string $bufferPath;

    private Container $container;

    private Repository $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bufferPath = sys_get_temp_dir().'/tracium-test-'.bin2hex(random_bytes(6)).'.ndjson';
        $this->container = new Container;
        Container::setInstance($this->container);
        $this->config = new Repository([
            'tracium' => [
                'endpoint' => 'https://ingest.tracium.test',
                'api_key' => 'trc_live_test',
                'timeout_seconds' => 2,
                'connect_timeout_seconds' => 0.5,
                'buffer_path' => $this->bufferPath,
                'batch' => [
                    'size' => 2,
                    'flush_interval_seconds' => 60,
                ],
            ],
        ]);
        $this->container->instance('config', $this->config);
    }

    protected function tearDown(): void
    {
        if (is_file($this->bufferPath)) {
            unlink($this->bufferPath);
        }

        Container::setInstance(null);
        parent::tearDown();
    }

    public function test_buffer_sends_events_as_a_batch_when_size_is_reached(): void
    {
        $http = new Factory;
        $http->fake();
        $transport = new FileBufferTransport(
            new HttpBatchTransport($http, $this->config),
            $this->config,
            $this->container,
        );

        $transport->send([['event_id' => 'event-1']]);
        $http->assertNothingSent();

        $transport->send([['event_id' => 'event-2']]);

        $http->assertSentCount(1);
        $http->assertSent(
            static fn ($request): bool => $request->url() === 'https://ingest.tracium.test/ingest/v1/events/batch'
                && $request['events'][0]['event_id'] === 'event-1'
                && $request['events'][1]['event_id'] === 'event-2',
        );
        self::assertSame('', file_get_contents($this->bufferPath));
    }
}
