<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Contracts;

use AdilAzhari\LaravelTrace\Trace\Trace;

interface TraceRecorder
{
    /**
     * Persist a trace. Implementations MUST be idempotent by
     * {@see Trace::$id}: recording the same trace ID more than once (e.g.
     * once when it starts as `Running`, again with its terminal status)
     * replaces the earlier record rather than duplicating it.
     */
    public function record(Trace $trace): void;
}
