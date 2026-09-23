<?php

declare(strict_types=1);

use AdilAzhari\LaravelTrace\Contracts\SpanReader;
use AdilAzhari\LaravelTrace\Read\SpanQuery;
use AdilAzhari\LaravelTrace\Span\Span;
use AdilAzhari\LaravelTrace\Span\SpanId;
use AdilAzhari\LaravelTrace\Span\SpanStatus;
use AdilAzhari\LaravelTrace\Span\SpanType;
use AdilAzhari\LaravelTrace\Trace\TraceId;
use Illuminate\Pagination\LengthAwarePaginator;

/*
 * The cross-driver read specification for spans. Runs against both storage
 * drivers; both must behave identically. Shared helpers live in
 * tests/Support/readerHelpers.php.
 */

it('finds a recorded span by id as a domain object', function (string $driver): void {
    useStorageDriver($driver);

    $traceId = TraceId::generate();
    recordTrace(makeTrace(id: $traceId));
    $recorded = recordSpan(makeSpan($traceId, 'database.query', SpanType::Database, attributes: ['db.rows' => 4]));

    $found = app(SpanReader::class)->find($recorded->id);

    expect($found)->toBeInstanceOf(Span::class)
        ->and($found?->id->value)->toBe($recorded->id->value)
        ->and($found?->traceId->value)->toBe($traceId->value)
        ->and($found?->type)->toBe(SpanType::Database)
        ->and($found?->attributes)->toBe(['db.rows' => 4]);
})->with('drivers');

it('returns null when finding an unknown span id', function (string $driver): void {
    useStorageDriver($driver);

    expect(app(SpanReader::class)->find(SpanId::generate()))->toBeNull();
})->with('drivers');

it('returns all spans for a trace oldest first', function (string $driver): void {
    useStorageDriver($driver);

    $traceId = TraceId::generate();
    recordTrace(makeTrace(id: $traceId));

    recordSpan(makeSpan($traceId, 'third', startedAt: new DateTimeImmutable('2026-01-01 12:00:03')));
    recordSpan(makeSpan($traceId, 'first', startedAt: new DateTimeImmutable('2026-01-01 12:00:01')));
    recordSpan(makeSpan($traceId, 'second', startedAt: new DateTimeImmutable('2026-01-01 12:00:02')));
    recordSpan(makeSpan(TraceId::generate(), 'other-trace'));

    $spans = app(SpanReader::class)->forTrace($traceId);

    expect($spans->pluck('name')->all())->toBe(['first', 'second', 'third']);
})->with('drivers');

it('returns only the direct children of a span oldest first', function (string $driver): void {
    useStorageDriver($driver);

    $traceId = TraceId::generate();
    recordTrace(makeTrace(id: $traceId));

    $parent = recordSpan(makeSpan($traceId, 'parent', startedAt: new DateTimeImmutable('2026-01-01 12:00:00')));
    $childB = recordSpan(makeSpan($traceId, 'child-b', parentId: $parent->id, startedAt: new DateTimeImmutable('2026-01-01 12:00:02')));
    $childA = recordSpan(makeSpan($traceId, 'child-a', parentId: $parent->id, startedAt: new DateTimeImmutable('2026-01-01 12:00:01')));
    $grandchild = recordSpan(makeSpan($traceId, 'grandchild', parentId: $childA->id, startedAt: new DateTimeImmutable('2026-01-01 12:00:03')));

    $children = app(SpanReader::class)->children($parent->id);

    expect($children->pluck('name')->all())->toBe(['child-a', 'child-b']);
})->with('drivers');

it('filters spans by trace, type and parent', function (string $driver): void {
    useStorageDriver($driver);

    $traceId = TraceId::generate();
    recordTrace(makeTrace(id: $traceId));

    $root = recordSpan(makeSpan($traceId, 'http.request', SpanType::Http));
    recordSpan(makeSpan($traceId, 'db-1', SpanType::Database, parentId: $root->id));
    recordSpan(makeSpan($traceId, 'db-2', SpanType::Database, parentId: $root->id));
    recordSpan(makeSpan($traceId, 'action', SpanType::Action, parentId: $root->id));

    expect(app(SpanReader::class)->get(SpanQuery::new()->whereTrace($traceId)->whereType(SpanType::Database)))
        ->toHaveCount(2)
        ->and(app(SpanReader::class)->get(SpanQuery::new()->whereParent($root->id))->pluck('name')->sort()->values()->all())
        ->toBe(['action', 'db-1', 'db-2']);
})->with('drivers');

it('filters spans to trace roots only', function (string $driver): void {
    useStorageDriver($driver);

    $traceId = TraceId::generate();
    recordTrace(makeTrace(id: $traceId));

    $root = recordSpan(makeSpan($traceId, 'http.request', SpanType::Http));
    recordSpan(makeSpan($traceId, 'child', SpanType::Action, parentId: $root->id));

    $roots = app(SpanReader::class)->get(SpanQuery::new()->whereTrace($traceId)->onlyRoots());

    expect($roots->pluck('name')->all())->toBe(['http.request']);
})->with('drivers');

it('filters spans by error presence and status', function (string $driver): void {
    useStorageDriver($driver);

    $traceId = TraceId::generate();
    recordTrace(makeTrace(id: $traceId));

    recordSpan(makeSpan($traceId, 'ok', status: SpanStatus::Completed));
    recordSpan(makeSpan($traceId, 'boom', status: SpanStatus::Failed, withError: true));

    expect(app(SpanReader::class)->get(SpanQuery::new()->whereTrace($traceId)->onlyErrors())->pluck('name')->all())
        ->toBe(['boom'])
        ->and(app(SpanReader::class)->get(SpanQuery::new()->whereTrace($traceId)->whereStatus(SpanStatus::Completed))->pluck('name')->all())
        ->toBe(['ok']);
})->with('drivers');

it('filters spans by attribute equality', function (string $driver): void {
    useStorageDriver($driver);

    $traceId = TraceId::generate();
    recordTrace(makeTrace(id: $traceId));

    recordSpan(makeSpan($traceId, 'cache-hit', attributes: ['cache.hit' => true]));
    recordSpan(makeSpan($traceId, 'cache-miss', attributes: ['cache.hit' => false]));

    expect(app(SpanReader::class)->get(SpanQuery::new()->whereTrace($traceId)->whereAttribute('cache.hit', true))->pluck('name')->all())
        ->toBe(['cache-hit']);
})->with('drivers');

it('paginates spans with length-aware metadata', function (string $driver): void {
    useStorageDriver($driver);

    $traceId = TraceId::generate();
    recordTrace(makeTrace(id: $traceId));

    foreach (range(1, 5) as $i) {
        recordSpan(makeSpan($traceId, "s{$i}", startedAt: new DateTimeImmutable("2026-01-01 12:00:0{$i}")));
    }

    $page = app(SpanReader::class)->paginate(SpanQuery::new()->whereTrace($traceId), perPage: 2, page: 3);

    expect($page)->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($page->total())->toBe(5)
        ->and($page->lastPage())->toBe(3)
        ->and($page->count())->toBe(1);
})->with('drivers');

it('counts spans matching a query', function (string $driver): void {
    useStorageDriver($driver);

    $traceId = TraceId::generate();
    recordTrace(makeTrace(id: $traceId));

    recordSpan(makeSpan($traceId, 'a', SpanType::Database));
    recordSpan(makeSpan($traceId, 'b', SpanType::Database));
    recordSpan(makeSpan($traceId, 'c', SpanType::Http));

    expect(app(SpanReader::class)->count(SpanQuery::new()->whereTrace($traceId)->whereType(SpanType::Database)))->toBe(2);
})->with('drivers');

it('round-trips status, timestamps and error details for a failed span', function (string $driver): void {
    useStorageDriver($driver);

    $traceId = TraceId::generate();
    recordTrace(makeTrace(id: $traceId));

    $recorded = recordSpan(makeSpan(
        $traceId,
        'action.charge',
        status: SpanStatus::Failed,
        startedAt: new DateTimeImmutable('2026-01-01 12:00:00.250000'),
        durationMs: 15,
        withError: true,
    ));

    $found = app(SpanReader::class)->find($recorded->id);

    expect($found?->status)->toBe(SpanStatus::Failed)
        ->and($found?->startedAt->format('Y-m-d\TH:i:s.u'))->toBe('2026-01-01T12:00:00.250000')
        ->and($found?->finishedAt)->not->toBeNull()
        ->and($found?->durationMs())->toBeGreaterThan(0.0)
        ->and($found?->error?->type)->toBe(RuntimeException::class)
        ->and($found?->error?->message)->toBe('boom');
})->with('drivers');

it('keeps trace_id and parent_id relationships intact through persistence', function (string $driver): void {
    useStorageDriver($driver);

    $traceId = TraceId::generate();
    recordTrace(makeTrace(id: $traceId));

    $parent = recordSpan(makeSpan($traceId, 'parent'));
    $child = recordSpan(makeSpan($traceId, 'child', parentId: $parent->id));

    $found = app(SpanReader::class)->find($child->id);

    expect($found?->traceId->value)->toBe($traceId->value)
        ->and($found?->parentId?->value)->toBe($parent->id->value);
})->with('drivers');
