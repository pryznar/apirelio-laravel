<?php

declare(strict_types=1);

namespace Apirelio\Laravel\Tests;

use Apirelio\Laravel\ApirelioServiceProvider;
use PHPUnit\Framework\TestCase;

final class ApirelioServiceProviderTest extends TestCase
{
    public function test_package_exposes_its_laravel_service_provider(): void
    {
        self::assertTrue(class_exists(ApirelioServiceProvider::class));
    }
}
