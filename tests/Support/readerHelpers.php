<?php

declare(strict_types=1);

use AdilAzhari\LaravelTrace\Contracts\SpanRecorder;
use AdilAzhari\LaravelTrace\Contracts\TraceRecorder;
use AdilAzhari\LaravelTrace\Span\Span;
use AdilAzhari\LaravelTrace\Span\SpanError;
use AdilAzhari\LaravelTrace\Span\SpanId;
use AdilAzhari\LaravelTrace\Span\SpanStatus;
use AdilAzhari\LaravelTrace\Span\SpanType;
use AdilAzhari\LaravelTrace\Trace\Trace;
use AdilAzhari\LaravelTrace\Trace\TraceError;
use AdilAzhari\LaravelTrace\Trace\TraceId;
use AdilAzhari\LaravelTrace\Trace\TraceStatus;

/**
 * Point the storage stack (recorders and readers) at one driver for the
 * rest of the test.
 */
function useStorageDriver(string $driver): void
{
    config()->set('laravel-trace.storage.driver', $driver);
}

function recordTrace(Trace $trace): Trace
{
    app(TraceRecorder::class)->record($trace);

    return $trace;
}

function recordSpan(Span $span): Span
{
    app(SpanRecorder::class)->record($span);

    return $span;
}

/**
 * @param  array<string, string|int|float|bool|null>  $attributes
 */
function makeTrace(
    string $name = 'http.request',
    TraceStatus $status = TraceStatus::Completed,
    ?DateTimeImmutable $startedAt = null,
    ?int $durationMs = 25,
    array $attributes = [],
    ?TraceId $id = null,
    bool $withError = false,
): Trace {
    $startedAt ??= new DateTimeImmutable;

    return new Trace(
        id: $id ?? TraceId::generate(),
        name: $name,
        status: $status,
        startedAt: $startedAt,
        finishedAt: $status === TraceStatus::Running
            ? null
            : $startedAt->modify(sprintf('+%d milliseconds', $durationMs ?? 0)),
        error: $withError
            ? TraceError::fromThrowable(new RuntimeException('boom'))
            : null,
        attributes: $attributes,
    );
}

/**
 * @param  array<string, string|int|float|bool|null>  $attributes
 */
function makeSpan(
    TraceId $traceId,
    string $name = 'action.work',
    SpanType $type = SpanType::Action,
    ?SpanId $parentId = null,
    SpanStatus $status = SpanStatus::Completed,
    ?DateTimeImmutable $startedAt = null,
    ?int $durationMs = 10,
    array $attributes = [],
    ?SpanId $id = null,
    bool $withError = false,
): Span {
    $startedAt ??= new DateTimeImmutable;

    return new Span(
        id: $id ?? SpanId::generate(),
        traceId: $traceId,
        parentId: $parentId,
        name: $name,
        type: $type,
        attributes: $attributes,
        status: $status,
        startedAt: $startedAt,
        finishedAt: $status === SpanStatus::Running
            ? null
            : $startedAt->modify(sprintf('+%d milliseconds', $durationMs ?? 0)),
        error: $withError
            ? SpanError::fromThrowable(new RuntimeException('boom'))
            : null,
    );
}
