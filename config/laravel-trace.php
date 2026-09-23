<?php

declare(strict_types=1);

return [

    /*
     * Master switch for the whole package. When false, no HTTP, database,
     * or queue instrumentation runs and inbound/outbound trace context is
     * never read or attached, regardless of the toggles below.
     */
    'enabled' => env('LARAVEL_TRACE_ENABLED', true),

    'storage' => [

        /*
         * Where traces and spans are persisted: "memory" (default; lives
         * only for the lifetime of the process/request - see the README's
         * "Memory driver limitations") or "database" (see storage.database
         * below).
         */
        'driver' => env('LARAVEL_TRACE_STORAGE_DRIVER', 'memory'),

        'database' => [

            /*
             * The database connection traces and spans are written to. Leave
             * as null to use the application's default connection.
             */
            'connection' => null,

            /*
             * When a write to the database fails (bad connection, missing
             * table, etc.), the exception is logged and swallowed so tracing
             * never breaks the host application. Set to false while
             * debugging your storage setup to let the exception surface.
             */
            'swallow_exceptions' => env('LARAVEL_TRACE_STORAGE_DATABASE_SWALLOW_EXCEPTIONS', true),

        ],

        'retention' => [

            /*
             * Whether `php artisan laravel-trace:prune` deletes anything
             * when run with no --days/--before override. Off by default so
             * a fresh install never starts deleting traces on its own.
             */
            'enabled' => env('LARAVEL_TRACE_RETENTION_ENABLED', false),

            /*
             * Traces (and their spans) older than this many days are
             * eligible for pruning. Only terminal traces (completed or
             * failed) are ever pruned - a still-running trace is never
             * deleted, however old.
             */
            'days' => env('LARAVEL_TRACE_RETENTION_DAYS', 7),

            /*
             * How many traces the pruner deletes per batch. Bounds how long
             * any single delete statement runs for.
             */
            'chunk_size' => env('LARAVEL_TRACE_RETENTION_CHUNK_SIZE', 500),

        ],
    ],

    /*
     * Instrumentation toggles: whether the package automatically records
     * spans for a given kind of work. This is independent of "storage"
     * above, which only controls *where* recorded traces/spans end up.
     * `instrumentation.database.enabled` gates SQL *query* instrumentation
     * (recording a span per query) - it is unrelated to
     * `storage.database.*`, which configures the database *storage* driver
     * traces/spans can optionally be persisted to.
     */
    'instrumentation' => [
        'database' => [
            'enabled' => env('LARAVEL_TRACE_DATABASE_QUERY_ENABLED', true),
        ],
    ],

    'queue' => [
        'enabled' => env('LARAVEL_TRACE_QUEUE_ENABLED', true),
    ],

    'http' => [

        /*
         * The header used to propagate trace context between services. An
         * inbound request carrying this header continues the upstream trace
         * instead of starting a fresh one.
         */
        'header' => 'X-Trace-Context',

        /*
         * When enabled, the active trace context is attached to every outbound
         * request made through Laravel's HTTP client so the receiving service
         * can continue the trace. Left off by default to avoid leaking trace
         * identifiers to third-party APIs.
         */
        'propagate_outbound' => env('LARAVEL_TRACE_HTTP_PROPAGATE_OUTBOUND', false),

    ],

];
