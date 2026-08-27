<?php

declare(strict_types=1);

namespace LaravelTrace\LaravelTrace;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Queue\Factory as QueueFactoryContract;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue as QueueFacade;
use Illuminate\Support\ServiceProvider;
use LaravelTrace\LaravelTrace\Console\Commands\LaravelTraceCommand;
use LaravelTrace\LaravelTrace\Context\InMemoryTraceContextStore;
use LaravelTrace\LaravelTrace\Contracts\SpanRecorder;
use LaravelTrace\LaravelTrace\Contracts\TraceContextStore;
use LaravelTrace\LaravelTrace\Contracts\Tracer as TracerContract;
use LaravelTrace\LaravelTrace\Contracts\TraceRecorder;
use LaravelTrace\LaravelTrace\Tracing\DatabaseQueryListener;
use LaravelTrace\LaravelTrace\Tracing\EventListenerTracer;
use LaravelTrace\LaravelTrace\Tracing\InMemorySpanRecorder;
use LaravelTrace\LaravelTrace\Tracing\InMemoryTraceRecorder;
use LaravelTrace\LaravelTrace\Tracing\QueueJobListener;
use LaravelTrace\LaravelTrace\Tracing\Tracer;
use LaravelTrace\LaravelTrace\Tracing\TracingEventDispatcher;

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
            fn (Application $app): SpanRecorder => $app->make(
                InMemorySpanRecorder::class,
            ),
        );

        $this->app->singleton(InMemoryTraceRecorder::class);

        $this->app->singleton(
            TraceRecorder::class,
            fn (Application $app): TraceRecorder => $app->make(
                InMemoryTraceRecorder::class,
            ),
        );

        $this->app->singleton(
            TracerContract::class,
            function (Application $app): Tracer {
                return new Tracer(
                    contextStore: $app->make(TraceContextStore::class),
                    spanRecorder: $app->make(SpanRecorder::class),
                    traceRecorder: $app->make(TraceRecorder::class),
                    config: $app->make(ConfigRepository::class),
                );
            },
        );

        $this->app->singleton(EventListenerTracer::class);

        $this->app->singleton(
            QueueJobListener::class,
            function (Application $app): QueueJobListener {
                return new QueueJobListener(
                    tracer: $app->make(TracerContract::class),
                    config: $app->make(ConfigRepository::class),
                );
            },
        );

        $this->app->singleton(
            'events',
            function (Application $app): TracingEventDispatcher {
                return (new TracingEventDispatcher(
                    listenerTracer: $app->make(EventListenerTracer::class),
                    container: $app,
                ))
                    ->setQueueResolver(
                        fn (): Queue => $app->make(QueueFactoryContract::class)->connection(),
                    )
                    ->setTransactionManagerResolver(
                        fn (): mixed => $app->bound('db.transactions')
                            ? $app->make('db.transactions')
                            : null,
                    );
            },
        );

        $this->app->singleton(
            DatabaseQueryListener::class,
            function (Application $app): DatabaseQueryListener {
                return new DatabaseQueryListener(
                    tracer: $app->make(TracerContract::class),
                    enabled: (bool) $app->make('config')->get(
                        'laravel-trace.database.enabled',
                        true,
                    ),
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

        QueueFacade::createPayloadUsing(
            function (): array {
                $context = $this->app->make(
                    TracerContract::class,
                )->context();

                if ($context === null) {
                    return [];
                }

                return [
                    'laravel-trace' => $context->toArray(),
                ];
            },
        );

        if (! $this->app->runningInConsole()) {
            return;
        }

        Event::listen(
            QueryExecuted::class,
            DatabaseQueryListener::class,
        );

        Event::listen(
            JobProcessing::class,
            [QueueJobListener::class, 'handleProcessing'],
        );

        Event::listen(
            JobProcessed::class,
            [QueueJobListener::class, 'handleProcessed'],
        );

        Event::listen(
            JobExceptionOccurred::class,
            [QueueJobListener::class, 'handleException'],
        );

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
