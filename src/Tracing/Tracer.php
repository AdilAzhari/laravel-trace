<?php

declare(strict_types=1);

namespace LaravelTrace\LaravelTrace\Tracing;

use LaravelTrace\LaravelTrace\Contracts\Tracer as TracerContract;
use LaravelTrace\LaravelTrace\Span\Span;
use LaravelTrace\LaravelTrace\Span\SpanType;
use LaravelTrace\LaravelTrace\Trace\Trace;

final class Tracer implements TracerContract
{
    public function start(string $name): Trace
    {
        return Trace::start($name);
    }

    public function startSpan(
        Trace $trace,
        string $name,
        SpanType $type,
    ): Span {
        return Span::start(
            traceId: $trace->id,
            name: $name,
            type: $type,
        );
    }
}
