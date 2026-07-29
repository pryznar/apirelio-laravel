<?php

declare(strict_types=1);

namespace Tracium\Laravel\Contracts;

interface EventTransport
{
    /** @param list<array<string, mixed>> $events */
    public function send(array $events): void;
}
