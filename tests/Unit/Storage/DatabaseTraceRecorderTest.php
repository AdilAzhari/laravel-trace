<?php

declare(strict_types=1);

use AdilAzhari\LaravelTrace\Models\TraceRecord;
use AdilAzhari\LaravelTrace\Storage\DatabaseTraceRecorder;
use AdilAzhari\LaravelTrace\Trace\Trace;
use AdilAzhari\LaravelTrace\Trace\TraceStatus;

it('persists a running trace', function (): void {
    $recorder = app(DatabaseTraceRecorder::class);

    $trace = Trace::start('CreateOrder', ['order.id' => 42]);

    $recorder->record($trace);

    $row = TraceRecord::query()->find($trace->id->value);

    expect($row)->not->toBeNull()
        ->and($row->name)->toBe('CreateOrder')
        ->and($row->status)->toBe(TraceStatus::Running->value)
        ->and($row->finished_at)->toBeNull()
        ->and($row->attributes)->toMatchArray(['order.id' => 42]);
});

it('persists a completed trace with its duration', function (): void {
    $recorder = app(DatabaseTraceRecorder::class);

    $trace = Trace::start('CreateOrder');
    $completed = $trace->complete(
        $trace->startedAt->modify('+50 milliseconds'),
    );

    $recorder->record($completed);

    $row = TraceRecord::query()->find($trace->id->value);

    expect($row->status)->toBe(TraceStatus::Completed->value)
        ->and($row->finished_at)->not->toBeNull()
        ->and($row->duration_ms)->toBeFloat()
        ->and($row->duration_ms)->toBeGreaterThan(0.0);
});

it('persists a failed trace with its error details', function (): void {
    $recorder = app(DatabaseTraceRecorder::class);

    $trace = Trace::start('CreateOrder');
    $failed = $trace->fail(
        new RuntimeException('Order processing failed.'),
        $trace->startedAt,
    );

    $recorder->record($failed);

    $row = TraceRecord::query()->find($trace->id->value);

    expect($row->status)->toBe(TraceStatus::Failed->value)
        ->and($row->error_type)->toBe(RuntimeException::class)
        ->and($row->error_message)->toBe('Order processing failed.');
});

it('is idempotent by trace id: a later record replaces the earlier one', function (): void {
    $recorder = app(DatabaseTraceRecorder::class);

    $trace = Trace::start('CreateOrder');
    $recorder->record($trace);
    $recorder->record($trace->complete($trace->startedAt));

    expect(TraceRecord::query()->count())->toBe(1)
        ->and(TraceRecord::query()->first()->status)
        ->toBe(TraceStatus::Completed->value);
});
