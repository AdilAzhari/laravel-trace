<?php

declare(strict_types=1);

use AdilAzhari\LaravelTrace\Read\OrderBy;
use AdilAzhari\LaravelTrace\Read\TraceQuery;
use AdilAzhari\LaravelTrace\Trace\TraceStatus;

it('starts empty and unfiltered', function (): void {
    $query = TraceQuery::new();

    expect($query->ids)->toBe([])
        ->and($query->names)->toBe([])
        ->and($query->statuses)->toBe([])
        ->and($query->startedAfter)->toBeNull()
        ->and($query->startedBefore)->toBeNull()
        ->and($query->minDurationMs)->toBeNull()
        ->and($query->maxDurationMs)->toBeNull()
        ->and($query->hasError)->toBeNull()
        ->and($query->attributes)->toBe([]);
});

it('is immutable: a where-clause returns a new instance and leaves the original untouched', function (): void {
    $base = TraceQuery::new();
    $filtered = $base->whereName('http.request');

    expect($filtered)->not->toBe($base)
        ->and($base->names)->toBe([])
        ->and($filtered->names)->toBe(['http.request']);
});

it('accumulates attribute equality filters under and-semantics', function (): void {
    $query = TraceQuery::new()
        ->whereAttribute('http.method', 'POST')
        ->whereAttribute('tenant.id', 42);

    expect($query->attributes)->toBe([
        'http.method' => 'POST',
        'tenant.id' => 42,
    ]);
});

it('captures each filter dimension', function (): void {
    $query = TraceQuery::new()
        ->whereId('01ID')
        ->whereName('http.request', 'queue.job')
        ->whereStatus(TraceStatus::Failed)
        ->minDurationMs(10)
        ->maxDurationMs(2000);

    expect($query->ids)->toBe(['01ID'])
        ->and($query->names)->toBe(['http.request', 'queue.job'])
        ->and($query->statuses)->toBe([TraceStatus::Failed])
        ->and($query->minDurationMs)->toBe(10.0)
        ->and($query->maxDurationMs)->toBe(2000.0);
});

it('normalises datetime bounds to immutable values', function (): void {
    $query = TraceQuery::new()
        ->startedAfter(new DateTime('2026-01-01 00:00:00'))
        ->startedBefore(new DateTimeImmutable('2026-01-02 00:00:00'));

    expect($query->startedAfter)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($query->startedBefore)->toBeInstanceOf(DateTimeImmutable::class);
});

it('captures the three error states', function (): void {
    expect(TraceQuery::new()->onlyErrors()->hasError)->toBeTrue()
        ->and(TraceQuery::new()->withoutErrors()->hasError)->toBeFalse()
        ->and(TraceQuery::new()->hasError)->toBeNull();
});

it('orders by started_at descending by default', function (): void {
    expect(TraceQuery::new()->orderBy)->toEqual(new OrderBy('started_at', 'desc'));
});

it('accepts a whitelisted sort field', function (): void {
    $query = TraceQuery::new()->orderByField('duration_ms', 'asc');

    expect($query->orderBy)->toEqual(new OrderBy('duration_ms', 'asc'));
});

it('rejects a sort field that is not a real column', function (): void {
    expect(fn () => TraceQuery::new()->orderByField('sql'))
        ->toThrow(InvalidArgumentException::class);
});
