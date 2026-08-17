<?php

declare(strict_types=1);

namespace LaravelTrace\LaravelTrace\Contracts;

use LaravelTrace\LaravelTrace\Context\TraceContext;
use LaravelTrace\LaravelTrace\Span\SpanType;
use LaravelTrace\LaravelTrace\Trace\Trace;
use LaravelTrace\LaravelTrace\Tracing\SpanScope;
use Throwable;

interface Tracer
{
    public function start(string $name): Trace;

    public function Span(
        string $name,
        SpanType $type,
        array $attributes = [],
    ): SpanScope;

    public function context(): ?TraceContext;

    public function setContext(TraceContext $context): void;

    public function clearContext(): void;

    public function completeTrace(Trace $trace): Trace;

    public function failTrace(
        Trace $trace,
        Throwable $exception,
    ): Trace;
}
