<?php

declare(strict_types=1);

use AdilAzhari\LaravelTrace\LaravelTraceServiceProvider;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Events\Dispatcher;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;

it('does not register the workbench demo routes', function (): void {
    $uris = collect($this->app->make('router')->getRoutes()->getRoutes())
        ->map(fn (RouteDefinition $route): string => $route->uri());

    expect($uris)->not->toContain('trace-test', 'trace-test-failure', 'trace-test/nested', 'trace-test/deep');
});

it('leaves the application on laravel\'s own event dispatcher', function (): void {
    expect(get_class($this->app->make('events')))->toBe(Dispatcher::class);
});

// A real application builds its HTTP kernel - and with it the Router, which
// captures the `events` dispatcher - before any service provider registers,
// and clears resolved facades just before providers register. Testbench
// builds the kernel later, which would hide a provider that swaps the
// dispatcher out, so this reproduces the real order explicitly.
it('keeps the dispatcher the router already holds when it registers', function (): void {
    $this->app->make(HttpKernel::class);
    $dispatcher = $this->app->make('events');

    Event::clearResolvedInstance('events');
    $this->app->register(LaravelTraceServiceProvider::class, force: true);

    expect($this->app->make('events'))->toBe($dispatcher);

    $matched = false;

    Event::listen(RouteMatched::class, function () use (&$matched): void {
        $matched = true;
    });

    Route::get('/dispatcher-identity-test', fn () => 'ok');

    $this->get('/dispatcher-identity-test')->assertOk();

    expect($matched)->toBeTrue();
});

it('registers instrumentation listeners in the console environment', function (): void {
    $events = $this->app->make('events');

    expect($events->hasListeners(QueryExecuted::class))->toBeTrue()
        ->and($events->hasListeners(JobProcessing::class))->toBeTrue()
        ->and($events->hasListeners(JobProcessed::class))->toBeTrue()
        ->and($events->hasListeners(JobExceptionOccurred::class))->toBeTrue();
});

it('registers instrumentation listeners when the application is serving HTTP requests', function (): void {
    putenv('APP_RUNNING_IN_CONSOLE=false');
    $_ENV['APP_RUNNING_IN_CONSOLE'] = 'false';
    $_SERVER['APP_RUNNING_IN_CONSOLE'] = 'false';

    try {
        $this->refreshApplication();

        expect($this->app->runningInConsole())->toBeFalse();

        $events = $this->app->make('events');

        expect($events->hasListeners(QueryExecuted::class))->toBeTrue()
            ->and($events->hasListeners(JobProcessing::class))->toBeTrue()
            ->and($events->hasListeners(JobProcessed::class))->toBeTrue()
            ->and($events->hasListeners(JobExceptionOccurred::class))->toBeTrue();
    } finally {
        putenv('APP_RUNNING_IN_CONSOLE');
        unset($_ENV['APP_RUNNING_IN_CONSOLE'], $_SERVER['APP_RUNNING_IN_CONSOLE']);
        $this->refreshApplication();
    }
});
