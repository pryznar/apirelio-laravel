<?php

declare(strict_types=1);

namespace Tracium\Laravel\Data;

final readonly class TraciumCustomer
{
    public function __construct(
        public string $id,
        public ?string $name = null,
        public ?string $plan = null,
    ) {}
}
