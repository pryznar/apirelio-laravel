<?php

declare(strict_types=1);

namespace Tracium\Laravel;

use Closure;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use Tracium\Laravel\Contracts\EventTransport;
use Tracium\Laravel\Data\TraciumApplication;
use Tracium\Laravel\Data\TraciumCustomer;
use Tracium\Laravel\Support\RouteNormalizer;

final class TraciumManager
{
    /** @var null|Closure(Request): ?TraciumCustomer */
    private ?Closure $customerResolver = null;

    /** @var null|Closure(Request): (TraciumApplication|string|null) */
    private ?Closure $applicationResolver = null;

    public function __construct(
        private readonly Container $container,
        private readonly Repository $config,
        private readonly EventTransport $transport,
        private readonly RouteNormalizer $routes,
    ) {}

    /** @param Closure(Request): ?TraciumCustomer $resolver */
    public function resolveCustomerUsing(Closure $resolver): self
    {
        $this->customerResolver = $resolver;

        return $this;
    }

    /** @param Closure(Request): (TraciumApplication|string|null) $resolver */
    public function resolveApplicationUsing(Closure $resolver): self
    {
        $this->applicationResolver = $resolver;

        return $this;
    }

    public function setErrorCode(string $errorCode): self
    {
        $this->request()?->attributes->set('tracium.error_code', mb_substr($errorCode, 0, 255));

        return $this;
    }

    /** @param array<string, bool|float|int|string|null> $metadata */
    public function addMetadata(array $metadata): self
    {
        $request = $this->request();
        if ($request === null) {
            return $this;
        }

        /** @var array<string, bool|float|int|string|null> $current */
        $current = $request->attributes->get('tracium.metadata', []);
        $request->attributes->set('tracium.metadata', array_merge($current, $this->safeMetadata($metadata)));

        return $this;
    }

    public function capture(
        Request $request,
        ?Response $response,
        int $durationMilliseconds,
        ?Throwable $exception = null,
    ): void {
        if (! $this->shouldCapture($request)) {
            return;
        }

        try {
            $customer = $this->resolveCustomer($request);
            $application = $this->resolveApplication($request);
            $status = $response?->getStatusCode() ?? 500;
            $metadata = $this->requestMetadata($request);

            if ($exception !== null) {
                $metadata['exception'] = $exception::class;
            }

            $event = [
                'event_id' => (string) Str::ulid(),
                'occurred_at' => CarbonImmutable::now('UTC')->format('Y-m-d\TH:i:s.v\Z'),
                'service' => (string) $this->config->get('tracium.service', 'laravel'),
                'environment' => (string) $this->config->get('tracium.environment', 'production'),
                'method' => strtoupper($request->method()),
                'route' => $this->routes->normalize($request),
                'route_name' => $this->routes->name($request),
                'status' => $status,
                'duration_ms' => max(0, $durationMilliseconds),
                'request_bytes' => max(0, (int) $request->server('CONTENT_LENGTH', '0')),
                'response_bytes' => $this->responseBytes($response),
                'customer_id' => $customer?->id,
                'customer_name' => $customer?->name,
                'customer_plan' => $customer?->plan,
                'application_id' => $application?->id,
                'application_name' => $application?->name,
                'api_version' => $request->header('x-api-version'),
                'sdk' => 'laravel',
                'sdk_version' => '0.1.0',
                'release' => $this->config->get('tracium.release'),
                'error_code' => $this->errorCode($request, $response),
                'metadata' => $metadata,
            ];

            $this->transport->send([$event]);
        } catch (Throwable $throwable) {
            try {
                if ($this->container->bound(ExceptionHandler::class)) {
                    $this->container->make(ExceptionHandler::class)->report($throwable);
                }
            } catch (Throwable) {
                // Analytics must never break the customer request.
            }
        } finally {
            $request->attributes->remove('tracium.error_code');
            $request->attributes->remove('tracium.metadata');
        }
    }

    private function shouldCapture(Request $request): bool
    {
        if (
            ! (bool) $this->config->get('tracium.enabled', true)
            || (string) $this->config->get('tracium.api_key') === ''
        ) {
            return false;
        }

        /** @var list<string> $paths */
        $paths = (array) $this->config->get('tracium.paths', ['api/*']);

        return $request->is(...$paths);
    }

    private function resolveCustomer(Request $request): ?TraciumCustomer
    {
        return $this->customerResolver === null ? null : ($this->customerResolver)($request);
    }

    private function resolveApplication(Request $request): ?TraciumApplication
    {
        if ($this->applicationResolver === null) {
            return null;
        }

        $application = ($this->applicationResolver)($request);

        return is_string($application) ? new TraciumApplication($application) : $application;
    }

    /** @return array<string, bool|float|int|string|null> */
    private function requestMetadata(Request $request): array
    {
        /** @var array<string, bool|float|int|string|null> $metadata */
        $metadata = $request->attributes->get('tracium.metadata', []);
        /** @var list<string> $headers */
        $headers = (array) $this->config->get('tracium.capture_headers', []);

        foreach ($headers as $header) {
            $value = $request->header($header);
            if (is_string($value) && $value !== '') {
                $metadata['header.'.strtolower($header)] = mb_substr($value, 0, 500);
            }
        }

        return $this->safeMetadata($metadata);
    }

    /** @param array<string, bool|float|int|string|null> $metadata
     *  @return array<string, bool|float|int|string|null>
     */
    private function safeMetadata(array $metadata): array
    {
        /** @var list<string> $allowed */
        $allowed = (array) $this->config->get('tracium.metadata_keys', []);
        $safe = [];

        foreach ($metadata as $key => $value) {
            if (
                str_starts_with($key, 'header.')
                || $key === 'exception'
                || in_array($key, $allowed, true)
            ) {
                $safe[mb_substr($key, 0, 120)] = is_string($value) ? mb_substr($value, 0, 1000) : $value;
            }
        }

        return $safe;
    }

    private function errorCode(Request $request, ?Response $response): ?string
    {
        $explicit = $request->attributes->get('tracium.error_code');
        if (is_string($explicit) && $explicit !== '') {
            return $explicit;
        }

        if ($response === null) {
            return null;
        }

        $content = $response->getContent();
        if (! is_string($content) || $content === '') {
            return null;
        }

        $decoded = json_decode($content, true);
        $path = (string) $this->config->get('tracium.error_code.json_path', 'error.code');
        $value = is_array($decoded) ? data_get($decoded, $path) : null;

        return is_scalar($value) ? mb_substr((string) $value, 0, 255) : null;
    }

    private function responseBytes(?Response $response): int
    {
        if ($response === null) {
            return 0;
        }

        $content = $response->getContent();

        return is_string($content) ? strlen($content) : 0;
    }

    private function request(): ?Request
    {
        if (! $this->container->bound('request')) {
            return null;
        }

        $request = $this->container->make('request');

        return $request instanceof Request ? $request : null;
    }
}
