<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Tests;

use AdilAzhari\LaravelTrace\LaravelTraceServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            LaravelTraceServiceProvider::class,
        ];
    }

    /**
     * Workbench route discovery (see WithWorkbench) registers the demo
     * routes in the `web` middleware group, whose cookie encryption needs
     * an application key.
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(
            __DIR__.'/../database/migrations',
        );
    }
}
