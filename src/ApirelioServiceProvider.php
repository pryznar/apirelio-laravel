<?php

declare(strict_types=1);

namespace Apirelio\Laravel;

use Apirelio\Laravel\Contracts\EventTransport;
use Apirelio\Laravel\Middleware\TrackApiRequest;
use Apirelio\Laravel\Transport\FileBufferTransport;
use Apirelio\Laravel\Transport\HttpBatchTransport;
use Apirelio\Laravel\Transport\QueueTransport;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

final class ApirelioServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/apirelio.php', 'apirelio');

        $this->app->singleton(HttpBatchTransport::class);
        $this->app->singleton(FileBufferTransport::class);

        $this->app->singleton(EventTransport::class, function ($app): EventTransport {
            return match ((string) $app['config']->get('apirelio.transport', 'queue')) {
                'sync' => $app->make(HttpBatchTransport::class),
                'queue' => $app->make(QueueTransport::class),
                'file-buffer' => $app->make(FileBufferTransport::class),
                default => throw new InvalidArgumentException('Unsupported Apirelio transport.'),
            };
        });

        $this->app->singleton(ApirelioManager::class);
        $this->app->alias(ApirelioManager::class, 'apirelio');
    }

    public function boot(): void
    {
        $configPath = (string) $this->app->make('path.config');

        $this->publishes([
            __DIR__.'/../config/apirelio.php' => $configPath.'/apirelio.php',
        ], 'apirelio-config');

        if ($this->app->bound(Router::class)) {
            $this->app->make(Router::class)->aliasMiddleware('apirelio', TrackApiRequest::class);
        }

        $this->app->afterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule
                ->call(fn () => $this->app->make(FileBufferTransport::class)->flushIfDue(force: true))
                ->everyMinute()
                ->name('apirelio:flush-buffer')
                ->withoutOverlapping();
        });
    }
}
