<?php

declare(strict_types=1);

namespace Apirelio\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Apirelio\Laravel\ApirelioManager resolveCustomerUsing(\Closure $resolver)
 * @method static \Apirelio\Laravel\ApirelioManager resolveApplicationUsing(\Closure $resolver)
 * @method static \Apirelio\Laravel\ApirelioManager setErrorCode(string $errorCode)
 * @method static \Apirelio\Laravel\ApirelioManager addMetadata(array<string, bool|float|int|string|null> $metadata)
 */
final class Apirelio extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'apirelio';
    }
}
