<?php

declare(strict_types=1);

namespace LaravelTrace\LaravelTrace\Contracts;

use LaravelTrace\LaravelTrace\Context\TraceContext;
use LaravelTrace\LaravelTrace\Span\SpanType;
use LaravelTrace\LaravelTrace\Trace\Trace;
use LaravelTrace\LaravelTrace\Tracing\SpanScope;

interface Tracer
{
    public function start(string $name): Trace;

    public function Span(
        string $name,
        SpanType $type,
    ): SpanScope;

    public function context(): ?TraceContext;

    public function setContext(TraceContext $context): void;

    public function clearContext(): void;
}
