<?php

declare(strict_types=1);

use AdilAzhari\LaravelTrace\Models\SpanRecord;
use AdilAzhari\LaravelTrace\Span\Span;
use AdilAzhari\LaravelTrace\Span\SpanId;
use AdilAzhari\LaravelTrace\Span\SpanStatus;
use AdilAzhari\LaravelTrace\Span\SpanType;
use AdilAzhari\LaravelTrace\Storage\SpanRecordMapper;
use AdilAzhari\LaravelTrace\Trace\TraceId;

it('round-trips a completed child span through persistence unchanged', function (): void {
    $mapper = new SpanRecordMapper;

    $traceId = TraceId::generate();
    $parentId = SpanId::generate();

    $span = Span::start($traceId, 'database.query', SpanType::Database, $parentId, [
        'db.rows' => 3,
        'db.cached' => false,
    ]);
    $completed = $span->completeSpan($span->startedAt->modify('+10 milliseconds'));

    $record = SpanRecord::query()->create([
        'id' => $completed->id->value,
        ...$mapper->toAttributes($completed),
    ]);

    $domain = $mapper->toDomain($record->fresh());

    expect($domain->id->value)->toBe($completed->id->value)
        ->and($domain->traceId->value)->toBe($traceId->value)
        ->and($domain->parentId?->value)->toBe($parentId->value)
        ->and($domain->name)->toBe('database.query')
        ->and($domain->type)->toBe(SpanType::Database)
        ->and($domain->status)->toBe(SpanStatus::Completed)
        ->and($domain->startedAt->format('Y-m-d\TH:i:s.u'))
        ->toBe($completed->startedAt->format('Y-m-d\TH:i:s.u'))
        ->and($domain->finishedAt?->format('Y-m-d\TH:i:s.u'))
        ->toBe($completed->finishedAt?->format('Y-m-d\TH:i:s.u'))
        ->and($domain->error)->toBeNull()
        ->and($domain->attributes)->toBe([
            'db.rows' => 3,
            'db.cached' => false,
        ]);
});

it('round-trips a root span with no parent', function (): void {
    $mapper = new SpanRecordMapper;

    $span = Span::start(TraceId::generate(), 'http.request', SpanType::Http);

    $record = SpanRecord::query()->create([
        'id' => $span->id->value,
        ...$mapper->toAttributes($span),
    ]);

    $domain = $mapper->toDomain($record->fresh());

    expect($domain->parentId)->toBeNull()
        ->and($domain->status)->toBe(SpanStatus::Running)
        ->and($domain->finishedAt)->toBeNull()
        ->and($domain->attributes)->toBe([]);
});

it('round-trips a failed span error', function (): void {
    $mapper = new SpanRecordMapper;

    $span = Span::start(TraceId::generate(), 'action.charge', SpanType::Action);
    $failed = $span->failSpan(new RuntimeException('gateway timeout'), $span->startedAt);

    $record = SpanRecord::query()->create([
        'id' => $failed->id->value,
        ...$mapper->toAttributes($failed),
    ]);

    $domain = $mapper->toDomain($record->fresh());

    expect($domain->status)->toBe(SpanStatus::Failed)
        ->and($domain->error?->type)->toBe(RuntimeException::class)
        ->and($domain->error?->message)->toBe('gateway timeout');
});
