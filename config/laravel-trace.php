<?php

declare(strict_types=1);

return [
    'enabled' => true,

    'storage' => [
        'driver' => 'memory',

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
            'swallow_exceptions' => true,

        ],

        'retention' => [

            /*
             * Whether `php artisan laravel-trace:prune` deletes anything
             * when run with no --days/--before override. Off by default so
             * a fresh install never starts deleting traces on its own.
             */
            'enabled' => false,

            /*
             * Traces (and their spans) older than this many days are
             * eligible for pruning. Only terminal traces (completed or
             * failed) are ever pruned - a still-running trace is never
             * deleted, however old.
             */
            'days' => 7,

            /*
             * How many traces the pruner deletes per batch. Bounds how long
             * any single delete statement runs for.
             */
            'chunk_size' => 500,

        ],
    ],

    'database' => [
        'enabled' => true,
    ],

    'queue' => [
        'enabled' => true,
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
        'propagate_outbound' => false,

    ],

];
