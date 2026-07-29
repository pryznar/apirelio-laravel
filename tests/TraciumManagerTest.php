<?php

declare(strict_types=1);

namespace Tracium\Laravel\Tests;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tracium\Laravel\Contracts\EventTransport;
use Tracium\Laravel\Data\TraciumApplication;
use Tracium\Laravel\Data\TraciumCustomer;
use Tracium\Laravel\Middleware\TrackApiRequest;
use Tracium\Laravel\Support\RouteNormalizer;
use Tracium\Laravel\TraciumManager;

final class TraciumManagerTest extends TestCase
{
    private Container $container;

    private Repository $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = new Container;
        Container::setInstance($this->container);
        $this->config = new Repository([
            'tracium' => [
                'enabled' => true,
                'api_key' => 'trc_live_test',
                'service' => 'billing-api',
                'environment' => 'production',
                'release' => '2026.07.29.1',
                'paths' => ['api/*'],
                'metadata_keys' => ['region'],
                'capture_headers' => ['x-api-version'],
                'error_code' => ['json_path' => 'error.code'],
            ],
        ]);
        $this->container->instance('config', $this->config);
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);
        parent::tearDown();
    }

    public function test_manager_builds_a_safe_normalized_request_event(): void
    {
        $transport = new CapturingTransport;
        $manager = new TraciumManager(
            $this->container,
            $this->config,
            $transport,
            new RouteNormalizer,
        );
        $request = Request::create(
            '/api/invoices/123?token=must-not-be-captured',
            'POST',
            server: [
                'CONTENT_LENGTH' => '814',
                'HTTP_X_API_VERSION' => 'v2',
                'HTTP_AUTHORIZATION' => 'Bearer customer-secret',
            ],
        );
        $this->container->instance('request', $request);

        $manager
            ->resolveCustomerUsing(
                static fn (): TraciumCustomer => new TraciumCustomer('company_128', 'Acme', 'growth'),
            )
            ->resolveApplicationUsing(
                static fn (): TraciumApplication => new TraciumApplication('integration_42', 'ERP'),
            )
            ->addMetadata(['region' => 'eu-central', 'password' => 'not-allowed'])
            ->setErrorCode('MISSING_RECIPIENT');

        $manager->capture(
            $request,
            new JsonResponse(['error' => ['code' => 'IGNORED']], 422),
            142,
        );

        self::assertCount(1, $transport->batches);
        $event = $transport->batches[0][0];
        self::assertSame('/api/invoices/{id}', $event['route']);
        self::assertSame('company_128', $event['customer_id']);
        self::assertSame('integration_42', $event['application_id']);
        self::assertSame('MISSING_RECIPIENT', $event['error_code']);
        self::assertSame(814, $event['request_bytes']);
        self::assertSame([
            'region' => 'eu-central',
            'header.x-api-version' => 'v2',
        ], $event['metadata']);
        self::assertStringNotContainsString('token', json_encode($event, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('customer-secret', json_encode($event, JSON_THROW_ON_ERROR));
    }

    public function test_middleware_never_breaks_request_when_transport_fails(): void
    {
        $manager = new TraciumManager(
            $this->container,
            $this->config,
            new ThrowingTransport,
            new RouteNormalizer,
        );
        $middleware = new TrackApiRequest($manager);
        $request = Request::create('/api/health', 'GET');
        $this->container->instance('request', $request);

        $response = $middleware->handle(
            $request,
            static fn (): JsonResponse => new JsonResponse(['ok' => true]),
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('{"ok":true}', $response->getContent());
    }
}

final class CapturingTransport implements EventTransport
{
    /** @var list<list<array<string, mixed>>> */
    public array $batches = [];

    public function send(array $events): void
    {
        $this->batches[] = $events;
    }
}

final class ThrowingTransport implements EventTransport
{
    public function send(array $events): void
    {
        throw new RuntimeException('Ingestion is unavailable.');
    }
}
