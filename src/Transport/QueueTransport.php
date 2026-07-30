<?php

declare(strict_types=1);

namespace Apirelio\Laravel\Transport;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Config\Repository;
use Apirelio\Laravel\Contracts\EventTransport;
use Apirelio\Laravel\Jobs\BufferApirelioEvents;

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
                (new BufferApirelioEvents($events))
                    ->onQueue((string) $this->config->get('apirelio.queue', 'default')),
            );
        }
    }
}
