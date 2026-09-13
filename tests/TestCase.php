<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Sashalenz\UkrPostApi\UkrPostApiServiceProvider;
use Spatie\LaravelData\LaravelDataServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [LaravelDataServiceProvider::class, UkrPostApiServiceProvider::class];
    }
}
