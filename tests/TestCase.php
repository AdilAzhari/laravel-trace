<?php

declare(strict_types=1);

namespace LaravelTrace\LaravelTrace\Tests;

use LaravelTrace\LaravelTrace\LaravelTraceServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelTraceServiceProvider::class,
        ];
    }
}
