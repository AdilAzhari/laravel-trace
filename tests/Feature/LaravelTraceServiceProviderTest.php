<?php

declare(strict_types=1);

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;

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
