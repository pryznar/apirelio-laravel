<?php

declare(strict_types=1);

namespace Apirelio\Laravel\Jobs;

use Apirelio\Laravel\Transport\FileBufferTransport;
use Illuminate\Contracts\Queue\ShouldQueue;

final class BufferApirelioEvents implements ShouldQueue
{
    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [1, 5, 15];

    public ?string $queue = null;

    /** @param list<array<string, mixed>> $events */
    public function __construct(public readonly array $events) {}

    public function onQueue(?string $queue): self
    {
        $this->queue = $queue;

        return $this;
    }

    public function handle(FileBufferTransport $transport): void
    {
        $transport->send($this->events);
    }
}
