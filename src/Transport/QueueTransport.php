<?php

declare(strict_types=1);

namespace Tracium\Laravel\Transport;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Config\Repository;
use Tracium\Laravel\Contracts\EventTransport;
use Tracium\Laravel\Jobs\BufferTraciumEvents;

final readonly class QueueTransport implements EventTransport
{
    public function __construct(
        private Dispatcher $dispatcher,
        private Repository $config,
    ) {}

    public function send(array $events): void
    {
        if ($events !== []) {
            $this->dispatcher->dispatch(
                (new BufferTraciumEvents($events))
                    ->onQueue((string) $this->config->get('tracium.queue', 'default')),
            );
        }
    }
}
