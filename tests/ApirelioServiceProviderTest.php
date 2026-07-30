<?php

declare(strict_types=1);

namespace Apirelio\Laravel\Tests;

use PHPUnit\Framework\TestCase;
use Apirelio\Laravel\ApirelioServiceProvider;

final class ApirelioServiceProviderTest extends TestCase
{
    public function testPackageExposesItsLaravelServiceProvider(): void
    {
        self::assertTrue(class_exists(ApirelioServiceProvider::class));
    }
}
