<?php

declare(strict_types=1);

namespace Tracium\Laravel\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use Tracium\Laravel\TraciumManager;

final readonly class TrackApiRequest
{
    public function __construct(private TraciumManager $tracium) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $this->tracium->capture($request, null, $this->duration($startedAt), $exception);

            throw $exception;
        }

        $this->tracium->capture($request, $response, $this->duration($startedAt));

        return $response;
    }

    private function duration(int $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1_000_000);
    }
}
