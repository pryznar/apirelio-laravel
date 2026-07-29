<?php

declare(strict_types=1);

namespace Tracium\Laravel;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Tracium\Laravel\Contracts\EventTransport;
use Tracium\Laravel\Middleware\TrackApiRequest;
use Tracium\Laravel\Transport\FileBufferTransport;
use Tracium\Laravel\Transport\HttpBatchTransport;
use Tracium\Laravel\Transport\QueueTransport;

final class TraciumServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/tracium.php', 'tracium');

        $this->app->singleton(HttpBatchTransport::class);
        $this->app->singleton(FileBufferTransport::class);

        $this->app->singleton(EventTransport::class, function ($app): EventTransport {
            return match ((string) $app['config']->get('tracium.transport', 'queue')) {
                'sync' => $app->make(HttpBatchTransport::class),
                'queue' => $app->make(QueueTransport::class),
                'file-buffer' => $app->make(FileBufferTransport::class),
                default => throw new InvalidArgumentException('Unsupported Tracium transport.'),
            };
        });

        $this->app->singleton(TraciumManager::class);
        $this->app->alias(TraciumManager::class, 'tracium');
    }

    public function boot(): void
    {
        $configPath = (string) $this->app->make('path.config');

        $this->publishes([
            __DIR__.'/../config/tracium.php' => $configPath.'/tracium.php',
        ], 'tracium-config');

        if ($this->app->bound(Router::class)) {
            $this->app->make(Router::class)->aliasMiddleware('tracium', TrackApiRequest::class);
        }

        $this->app->afterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule
                ->call(fn () => $this->app->make(FileBufferTransport::class)->flushIfDue(force: true))
                ->everyMinute()
                ->name('tracium:flush-buffer')
                ->withoutOverlapping();
        });
    }
}
