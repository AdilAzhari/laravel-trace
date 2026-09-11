<?php

declare(strict_types=1);

use AdilAzhari\LaravelTrace\Models\TraceRecord;
use AdilAzhari\LaravelTrace\Storage\TraceRecordMapper;
use AdilAzhari\LaravelTrace\Trace\Trace;
use AdilAzhari\LaravelTrace\Trace\TraceStatus;

it('round-trips a completed trace through persistence unchanged', function (): void {
    $mapper = new TraceRecordMapper;

    $trace = Trace::start('CreateOrder', [
        'order.id' => 42,
        'flagged' => true,
        'note' => null,
    ]);
    $completed = $trace->complete($trace->startedAt->modify('+25 milliseconds'));

    $record = TraceRecord::query()->create([
        'id' => $completed->id->value,
        ...$mapper->toAttributes($completed),
    ]);

    $domain = $mapper->toDomain($record->fresh());

    expect($domain->id->value)->toBe($completed->id->value)
        ->and($domain->name)->toBe('CreateOrder')
        ->and($domain->status)->toBe(TraceStatus::Completed)
        ->and($domain->startedAt->format('Y-m-d\TH:i:s.u'))
        ->toBe($completed->startedAt->format('Y-m-d\TH:i:s.u'))
        ->and($domain->finishedAt?->format('Y-m-d\TH:i:s.u'))
        ->toBe($completed->finishedAt?->format('Y-m-d\TH:i:s.u'))
        ->and($domain->error)->toBeNull()
        ->and($domain->attributes)->toBe([
            'order.id' => 42,
            'flagged' => true,
            'note' => null,
        ]);
});

it('round-trips a running trace with no finish time', function (): void {
    $mapper = new TraceRecordMapper;

    $trace = Trace::start('CreateOrder');

    $record = TraceRecord::query()->create([
        'id' => $trace->id->value,
        ...$mapper->toAttributes($trace),
    ]);

    $domain = $mapper->toDomain($record->fresh());

    expect($domain->status)->toBe(TraceStatus::Running)
        ->and($domain->finishedAt)->toBeNull()
        ->and($domain->attributes)->toBe([]);
});

it('round-trips a failed trace error', function (): void {
    $mapper = new TraceRecordMapper;

    $trace = Trace::start('CreateOrder');
    $failed = $trace->fail(new RuntimeException('order processing failed'), $trace->startedAt);

    $record = TraceRecord::query()->create([
        'id' => $failed->id->value,
        ...$mapper->toAttributes($failed),
    ]);

    $domain = $mapper->toDomain($record->fresh());

    expect($domain->status)->toBe(TraceStatus::Failed)
        ->and($domain->error?->type)->toBe(RuntimeException::class)
        ->and($domain->error?->message)->toBe('order processing failed')
        ->and($domain->error?->line)->toBeInt();
});
