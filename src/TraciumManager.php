<?php

declare(strict_types=1);

namespace Tracium\Laravel;

use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use Tracium\Core\Data\EventContext;
use Tracium\Core\ErrorCodeExtractor;
use Tracium\Core\EventFactory;
use Tracium\Core\MetadataSanitizer;
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
        private readonly EventFactory $events = new EventFactory(),
        private readonly MetadataSanitizer $metadata = new MetadataSanitizer(),
        private readonly ErrorCodeExtractor $errorCodes = new ErrorCodeExtractor(),
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
        $request->attributes->set('tracium.metadata', array_merge($current, $this->sanitizeMetadata($metadata)));

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
            $metadata = $this->requestMetadata($request);
            if ($exception !== null) {
                $metadata['exception'] = $exception::class;
            }
            $metadata = $this->sanitizeMetadata($metadata);

            $this->transport->send([$this->events->create(new EventContext(
                service: (string) $this->config->get('tracium.service', 'laravel'),
                environment: (string) $this->config->get('tracium.environment', 'production'),
                method: $request->method(),
                route: $this->routes->normalize($request),
                routeName: $this->routes->name($request),
                status: $response?->getStatusCode() ?? 500,
                durationMilliseconds: $durationMilliseconds,
                requestBytes: (int) $request->server('CONTENT_LENGTH', '0'),
                responseBytes: $this->responseBytes($response),
                customer: $customer,
                application: $application,
                apiVersion: $this->stringOrNull($request->header('x-api-version')),
                sdk: 'laravel',
                sdkVersion: '0.1.2',
                release: $this->stringOrNull($this->config->get('tracium.release')),
                errorCode: $this->errorCode($request, $response),
                metadata: $metadata,
            ))]);
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

        return $metadata;
    }

    /** @param array<string, bool|float|int|string|null> $metadata
     *  @return array<string, bool|float|int|string|null>
     */
    private function sanitizeMetadata(array $metadata): array
    {
        /** @var list<string> $allowed */
        $allowed = (array) $this->config->get('tracium.metadata_keys', []);

        return $this->metadata->sanitize($metadata, $allowed);
    }

    private function errorCode(Request $request, ?Response $response): ?string
    {
        $path = (string) $this->config->get('tracium.error_code.json_path', 'error.code');
        $explicit = $request->attributes->get('tracium.error_code');
        $content = $response?->getContent();

        return $this->errorCodes->extract(
            is_string($explicit) ? $explicit : null,
            is_string($content) ? $content : null,
            $path,
        );
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

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
