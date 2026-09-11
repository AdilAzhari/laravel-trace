<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace;

use AdilAzhari\LaravelTrace\Console\Commands\LaravelTraceCommand;
use AdilAzhari\LaravelTrace\Console\Commands\PruneTracesCommand;
use AdilAzhari\LaravelTrace\Context\InMemoryTraceContextStore;
use AdilAzhari\LaravelTrace\Contracts\SpanReader;
use AdilAzhari\LaravelTrace\Contracts\SpanRecorder;
use AdilAzhari\LaravelTrace\Contracts\TraceContextStore;
use AdilAzhari\LaravelTrace\Contracts\TracePruner;
use AdilAzhari\LaravelTrace\Contracts\Tracer as TracerContract;
use AdilAzhari\LaravelTrace\Contracts\TraceReader;
use AdilAzhari\LaravelTrace\Contracts\TraceRecorder;
use AdilAzhari\LaravelTrace\Http\Middleware\TraceRequest;
use AdilAzhari\LaravelTrace\Read\DatabaseSpanReader;
use AdilAzhari\LaravelTrace\Read\DatabaseTraceReader;
use AdilAzhari\LaravelTrace\Read\InMemorySpanReader;
use AdilAzhari\LaravelTrace\Read\InMemoryTraceReader;
use AdilAzhari\LaravelTrace\Read\StorageDrivenSpanReader;
use AdilAzhari\LaravelTrace\Read\StorageDrivenTraceReader;
use AdilAzhari\LaravelTrace\Retention\DatabaseTracePruner;
use AdilAzhari\LaravelTrace\Retention\InMemoryTracePruner;
use AdilAzhari\LaravelTrace\Storage\DatabaseSpanRecorder;
use AdilAzhari\LaravelTrace\Storage\DatabaseTraceRecorder;
use AdilAzhari\LaravelTrace\Storage\SpanRecordMapper;
use AdilAzhari\LaravelTrace\Storage\StorageDrivenSpanRecorder;
use AdilAzhari\LaravelTrace\Storage\StorageDrivenTraceRecorder;
use AdilAzhari\LaravelTrace\Storage\TraceRecordMapper;
use AdilAzhari\LaravelTrace\Tracing\DatabaseQueryListener;
use AdilAzhari\LaravelTrace\Tracing\EventListenerTracer;
use AdilAzhari\LaravelTrace\Tracing\InMemorySpanRecorder;
use AdilAzhari\LaravelTrace\Tracing\InMemorySpanStore;
use AdilAzhari\LaravelTrace\Tracing\InMemoryTraceRecorder;
use AdilAzhari\LaravelTrace\Tracing\InMemoryTraceStore;
use AdilAzhari\LaravelTrace\Tracing\QueueJobListener;
use AdilAzhari\LaravelTrace\Tracing\Tracer;
use AdilAzhari\LaravelTrace\Tracing\TracingEventDispatcher;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Queue\Factory as QueueFactoryContract;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue as QueueFacade;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;

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

        $this->app->singleton(TraceRecordMapper::class);
        $this->app->singleton(SpanRecordMapper::class);

        $this->app->singleton(InMemoryTraceStore::class);
        $this->app->singleton(InMemorySpanStore::class);

        $this->app->singleton(InMemorySpanRecorder::class);
        $this->app->singleton(DatabaseSpanRecorder::class);
        $this->app->singleton(StorageDrivenSpanRecorder::class);

        $this->app->singleton(
            SpanRecorder::class,
            fn (Application $app): SpanRecorder => $app->make(
                StorageDrivenSpanRecorder::class,
            ),
        );

        $this->app->singleton(InMemoryTraceRecorder::class);
        $this->app->singleton(DatabaseTraceRecorder::class);
        $this->app->singleton(StorageDrivenTraceRecorder::class);

        $this->app->singleton(
            TraceRecorder::class,
            fn (Application $app): TraceRecorder => $app->make(
                StorageDrivenTraceRecorder::class,
            ),
        );

        $this->app->singleton(InMemorySpanReader::class);
        $this->app->singleton(DatabaseSpanReader::class);
        $this->app->singleton(StorageDrivenSpanReader::class);

        $this->app->singleton(
            SpanReader::class,
            fn (Application $app): SpanReader => $app->make(
                StorageDrivenSpanReader::class,
            ),
        );

        $this->app->singleton(InMemoryTraceReader::class);
        $this->app->singleton(DatabaseTraceReader::class);
        $this->app->singleton(StorageDrivenTraceReader::class);

        $this->app->singleton(
            TraceReader::class,
            fn (Application $app): TraceReader => $app->make(
                StorageDrivenTraceReader::class,
            ),
        );

        $this->app->singleton(InMemoryTracePruner::class);
        $this->app->singleton(DatabaseTracePruner::class);

        // No StorageDriven*Pruner proxy: unlike the recorder/reader
        // contracts, nothing resolves TracePruner during container boot, so
        // there is no early-resolution problem to work around. A command
        // (or any other caller) resolves it well after config is settled,
        // so a plain factory that reads the driver at resolution time is
        // enough - bound, not singleton, so it never goes stale.
        $this->app->bind(
            TracePruner::class,
            function (Application $app): TracePruner {
                $driver = $app->make(ConfigRepository::class)
                    ->get('laravel-trace.storage.driver', 'memory');

                return match ($driver) {
                    'memory' => $app->make(InMemoryTracePruner::class),
                    'database' => $app->make(DatabaseTracePruner::class),
                    default => throw new InvalidArgumentException(
                        sprintf(
                            'Unknown laravel-trace storage driver [%s]. Expected "memory" or "database".',
                            is_scalar($driver) ? (string) $driver : get_debug_type($driver),
                        ),
                    ),
                };
            },
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
                    config: $app->make(ConfigRepository::class),
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

        $this->propagateContextToOutboundRequests();

        // Instrumentation listeners must be registered in every environment,
        // not just the console. Database queries and sync-queue jobs run
        // during HTTP requests, and gating these behind runningInConsole()
        // silently disables database and queue tracing on the request path.
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
            PruneTracesCommand::class,
        ]);
    }

    /**
     * Attach the active trace context to outbound HTTP client requests so a
     * downstream service can continue the trace. Mirrors the queue payload
     * propagation. Opt-in, and an explicit header is never overwritten.
     */
    private function propagateContextToOutboundRequests(): void
    {
        if (! class_exists(HttpFactory::class)) {
            return;
        }

        Http::globalRequestMiddleware(
            function (RequestInterface $request): RequestInterface {
                $config = $this->app->make(ConfigRepository::class);

                if (! (bool) $config->get('laravel-trace.enabled', true)) {
                    return $request;
                }

                if (! (bool) $config->get('laravel-trace.http.propagate_outbound', false)) {
                    return $request;
                }

                $header = $config->get(
                    'laravel-trace.http.header',
                    TraceRequest::DEFAULT_HEADER,
                );

                $header = is_string($header) && $header !== ''
                    ? $header
                    : TraceRequest::DEFAULT_HEADER;

                if ($request->hasHeader($header)) {
                    return $request;
                }

                $context = $this->app->make(TracerContract::class)->context();

                if ($context === null) {
                    return $request;
                }

                return $request->withHeader($header, $context->toHeader());
            },
        );
    }
}
