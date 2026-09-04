<?php

declare(strict_types=1);

return [
    'enabled' => true,

    'storage' => [
        'driver' => 'memory',
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
