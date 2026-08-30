<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Contracts;

use AdilAzhari\LaravelTrace\Context\TraceContext;
use AdilAzhari\LaravelTrace\Span\SpanType;
use AdilAzhari\LaravelTrace\Trace\Trace;
use AdilAzhari\LaravelTrace\Tracing\SpanScope;
use Throwable;

interface Tracer
{
    /**
     * @param  array<string, string|int|float|bool|null>  $attributes
     */
    public function start(
        string $name,
        array $attributes = [],
    ): Trace;

    /**
     * @param  array<string, string|int|float|bool|null>  $attributes
     */
    public function span(
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
