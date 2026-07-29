<?php

declare(strict_types=1);

namespace Tracium\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Tracium\Laravel\TraciumManager resolveCustomerUsing(\Closure $resolver)
 * @method static \Tracium\Laravel\TraciumManager resolveApplicationUsing(\Closure $resolver)
 * @method static \Tracium\Laravel\TraciumManager setErrorCode(string $errorCode)
 * @method static \Tracium\Laravel\TraciumManager addMetadata(array<string, bool|float|int|string|null> $metadata)
 */
final class Tracium extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'tracium';
    }
}
