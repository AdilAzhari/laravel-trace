<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Contracts;

use AdilAzhari\LaravelTrace\Context\TraceContext;
use AdilAzhari\LaravelTrace\Span\SpanType;
use AdilAzhari\LaravelTrace\Trace\Trace;
use AdilAzhari\LaravelTrace\Tracing\SpanScope;
use DateTimeImmutable;
use LogicException;
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
     * $startedAt backdates the span's start time, for instrumentation hooked
     * to an event that only fires after the work already finished (e.g. a
     * database query) - without it, the span's own duration would reflect
     * the near-zero time it takes to record it rather than the real elapsed
     * time. Defaults to now.
     *
     * @param  array<string, string|int|float|bool|null>  $attributes
     *
     * @throws LogicException when no trace is active
     */
    public function span(
        string $name,
        SpanType $type,
        array $attributes = [],
        ?DateTimeImmutable $startedAt = null,
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
