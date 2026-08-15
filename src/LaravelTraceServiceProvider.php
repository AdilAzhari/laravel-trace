<?php

declare(strict_types=1);

namespace LaravelTrace\LaravelTrace;

use Illuminate\Support\ServiceProvider;
use LaravelTrace\LaravelTrace\Console\Commands\LaravelTraceCommand;
use LaravelTrace\LaravelTrace\Context\InMemoryTraceContextStore;
use LaravelTrace\LaravelTrace\Contracts\SpanRecorder;
use LaravelTrace\LaravelTrace\Contracts\TraceContextStore;
use LaravelTrace\LaravelTrace\Contracts\Tracer as TracerContract;
use LaravelTrace\LaravelTrace\Tracing\InMemorySpanRecorder;
use LaravelTrace\LaravelTrace\Tracing\Tracer;

class LaravelTraceServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/laravel-trace.php',
            'laravel-trace',
        );

        $this->app->singleton(LaravelTrace::class);

        $this->app->singleton(
            TraceContextStore::class,
            InMemoryTraceContextStore::class,
        );

        $this->app->singleton(InMemorySpanRecorder::class);

        $this->app->singleton(
            SpanRecorder::class,
            fn ($app): SpanRecorder => $app->make(
                InMemorySpanRecorder::class,
            ),
        );

        $this->app->singleton(
            TracerContract::class,
            function ($app): Tracer {
                return new Tracer(
                    contextStore: $app->make(TraceContextStore::class),
                    spanRecorder: $app->make(SpanRecorder::class),
                );
            },
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../workbench/routes/web.php');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'laravel-trace');

        $this->loadTranslationsFrom(__DIR__.'/../lang', 'laravel-trace');

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/laravel-trace.php' => config_path('laravel-trace.php'),
        ], ['laravel-trace', 'laravel-trace-config']);

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/laravel-trace'),
        ], ['laravel-trace', 'laravel-trace-views']);

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/laravel-trace'),
        ], ['laravel-trace', 'laravel-trace-lang']);

        $this->publishes([
            __DIR__.'/../public' => public_path('vendor/laravel-trace'),
        ], ['laravel-trace', 'laravel-trace-assets']);

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], ['laravel-trace', 'laravel-trace-migrations']);

        $this->commands([
            LaravelTraceCommand::class,
        ]);
    }
}
