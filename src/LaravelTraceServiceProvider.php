<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace;

use AdilAzhari\LaravelTrace\Console\Commands\LaravelTraceCommand;
use AdilAzhari\LaravelTrace\Context\InMemoryTraceContextStore;
use AdilAzhari\LaravelTrace\Contracts\SpanRecorder;
use AdilAzhari\LaravelTrace\Contracts\TraceContextStore;
use AdilAzhari\LaravelTrace\Contracts\Tracer as TracerContract;
use AdilAzhari\LaravelTrace\Contracts\TraceRecorder;
use AdilAzhari\LaravelTrace\Http\Middleware\TraceRequest;
use AdilAzhari\LaravelTrace\Tracing\DatabaseQueryListener;
use AdilAzhari\LaravelTrace\Tracing\EventListenerTracer;
use AdilAzhari\LaravelTrace\Tracing\InMemorySpanRecorder;
use AdilAzhari\LaravelTrace\Tracing\InMemoryTraceRecorder;
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
