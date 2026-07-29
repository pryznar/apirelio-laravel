<?php

declare(strict_types=1);

namespace Tracium\Laravel\Tests;

use PHPUnit\Framework\TestCase;
use Tracium\Laravel\TraciumServiceProvider;

final class TraciumServiceProviderTest extends TestCase
{
    public function testPackageExposesItsLaravelServiceProvider(): void
    {
        self::assertTrue(class_exists(TraciumServiceProvider::class));
    }
}
