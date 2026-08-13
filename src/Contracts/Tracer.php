<?php

declare(strict_types=1);

namespace LaravelTrace\LaravelTrace\Contracts;

use LaravelTrace\LaravelTrace\Span\Span;
use LaravelTrace\LaravelTrace\Span\SpanType;
use LaravelTrace\LaravelTrace\Trace\Trace;

interface Tracer
{
    public function start(string $name): Trace;

    public function startSpan(
        Trace $trace,
        string $name,
        SpanType $type,
    ): Span;
}
