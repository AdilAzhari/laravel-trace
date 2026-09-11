<?php

declare(strict_types=1);

use AdilAzhari\LaravelTrace\Contracts\TraceReader;
use AdilAzhari\LaravelTrace\Read\TraceQuery;
use AdilAzhari\LaravelTrace\Trace\Trace;
use AdilAzhari\LaravelTrace\Trace\TraceId;
use AdilAzhari\LaravelTrace\Trace\TraceStatus;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/*
 * The cross-driver read specification for traces. Every test runs against
 * both storage drivers and must behave identically: data goes in through
 * the write path (the driver's recorder) and comes back through the read
 * path (the driver's reader). "memory" and "database" are the same
 * behaviour over different substrates.
 *
 * Shared helpers (useStorageDriver, recordTrace, makeTrace) live in
 * tests/Support/readerHelpers.php.
 */

it('finds a recorded trace by id and returns it as a domain object', function (string $driver): void {
    useStorageDriver($driver);

    $recorded = recordTrace(makeTrace('checkout', attributes: ['order.id' => 7]));

    $found = app(TraceReader::class)->find($recorded->id);

    expect($found)->toBeInstanceOf(Trace::class)
        ->and($found?->id->value)->toBe($recorded->id->value)
        ->and($found?->name)->toBe('checkout')
        ->and($found?->status)->toBe(TraceStatus::Completed)
        ->and($found?->attributes)->toBe(['order.id' => 7]);
})->with('drivers');

it('returns null when finding an unknown trace id', function (string $driver): void {
    useStorageDriver($driver);

    expect(app(TraceReader::class)->find(TraceId::generate()))->toBeNull();
})->with('drivers');

it('filters traces by name', function (string $driver): void {
    useStorageDriver($driver);

    recordTrace(makeTrace('http.request'));
    recordTrace(makeTrace('http.request'));
    recordTrace(makeTrace('queue.job'));

    $results = app(TraceReader::class)->get(TraceQuery::new()->whereName('http.request'));

    expect($results)->toBeInstanceOf(Collection::class)
        ->toHaveCount(2)
        ->and($results->pluck('name')->unique()->all())->toBe(['http.request']);
})->with('drivers');

it('filters traces by status', function (string $driver): void {
    useStorageDriver($driver);

    recordTrace(makeTrace(status: TraceStatus::Completed));
    recordTrace(makeTrace(status: TraceStatus::Failed, withError: true));
    recordTrace(makeTrace(status: TraceStatus::Running));

    $failed = app(TraceReader::class)->get(TraceQuery::new()->whereStatus(TraceStatus::Failed));

    expect($failed)->toHaveCount(1)
        ->and($failed->first()?->status)->toBe(TraceStatus::Failed);
})->with('drivers');

it('filters traces by a half-open started_at range', function (string $driver): void {
    useStorageDriver($driver);

    recordTrace(makeTrace(name: 'before', startedAt: new DateTimeImmutable('2026-01-01 09:00:00')));
    recordTrace(makeTrace(name: 'inside', startedAt: new DateTimeImmutable('2026-01-01 10:00:00')));
    recordTrace(makeTrace(name: 'boundary', startedAt: new DateTimeImmutable('2026-01-01 11:00:00')));

    $results = app(TraceReader::class)->get(
        TraceQuery::new()
            ->startedAfter(new DateTimeImmutable('2026-01-01 10:00:00'))
            ->startedBefore(new DateTimeImmutable('2026-01-01 11:00:00')),
    );

    expect($results->pluck('name')->all())->toBe(['inside']);
})->with('drivers');

it('filters traces by a duration range', function (string $driver): void {
    useStorageDriver($driver);

    recordTrace(makeTrace(name: 'fast', durationMs: 10));
    recordTrace(makeTrace(name: 'medium', durationMs: 100));
    recordTrace(makeTrace(name: 'slow', durationMs: 1000));

    $results = app(TraceReader::class)->get(
        TraceQuery::new()->minDurationMs(50)->maxDurationMs(500),
    );

    expect($results->pluck('name')->all())->toBe(['medium']);
})->with('drivers');

it('filters traces by error presence', function (string $driver): void {
    useStorageDriver($driver);

    recordTrace(makeTrace(name: 'ok', status: TraceStatus::Completed));
    recordTrace(makeTrace(name: 'broke', status: TraceStatus::Failed, withError: true));

    expect(app(TraceReader::class)->get(TraceQuery::new()->onlyErrors())->pluck('name')->all())
        ->toBe(['broke'])
        ->and(app(TraceReader::class)->get(TraceQuery::new()->withoutErrors())->pluck('name')->all())
        ->toBe(['ok']);
})->with('drivers');

it('filters traces by attribute equality across scalar types', function (string $driver): void {
    useStorageDriver($driver);

    recordTrace(makeTrace(name: 'post', attributes: ['http.method' => 'POST', 'retries' => 0]));
    recordTrace(makeTrace(name: 'get', attributes: ['http.method' => 'GET', 'retries' => 3]));

    expect(app(TraceReader::class)->get(TraceQuery::new()->whereAttribute('http.method', 'POST'))->pluck('name')->all())
        ->toBe(['post'])
        ->and(app(TraceReader::class)->get(TraceQuery::new()->whereAttribute('retries', 3))->pluck('name')->all())
        ->toBe(['get']);
})->with('drivers');

it('combines multiple filters with and-semantics', function (string $driver): void {
    useStorageDriver($driver);

    recordTrace(makeTrace(name: 'http.request', status: TraceStatus::Failed, durationMs: 800, withError: true));
    recordTrace(makeTrace(name: 'http.request', status: TraceStatus::Completed, durationMs: 800));
    recordTrace(makeTrace(name: 'queue.job', status: TraceStatus::Failed, durationMs: 800, withError: true));

    $results = app(TraceReader::class)->get(
        TraceQuery::new()
            ->whereName('http.request')
            ->whereStatus(TraceStatus::Failed)
            ->minDurationMs(500),
    );

    expect($results)->toHaveCount(1);
})->with('drivers');

it('orders by started_at descending with an id tiebreak by default', function (string $driver): void {
    useStorageDriver($driver);

    $at = new DateTimeImmutable('2026-01-01 12:00:00');
    recordTrace(makeTrace(name: 'a', startedAt: $at, id: new TraceId('00000000000000000000000001')));
    recordTrace(makeTrace(name: 'b', startedAt: $at, id: new TraceId('00000000000000000000000002')));
    recordTrace(makeTrace(name: 'c', startedAt: new DateTimeImmutable('2026-01-01 13:00:00'), id: new TraceId('00000000000000000000000000')));

    $order = app(TraceReader::class)->get(TraceQuery::new())->pluck('name')->all();

    expect($order)->toBe(['c', 'b', 'a']);
})->with('drivers');

it('orders by a whitelisted field in the requested direction', function (string $driver): void {
    useStorageDriver($driver);

    recordTrace(makeTrace(name: 'slow', durationMs: 900));
    recordTrace(makeTrace(name: 'fast', durationMs: 10));
    recordTrace(makeTrace(name: 'medium', durationMs: 200));

    $ascending = app(TraceReader::class)
        ->get(TraceQuery::new()->orderByField('duration_ms', 'asc'))
        ->pluck('name')->all();

    expect($ascending)->toBe(['fast', 'medium', 'slow']);
})->with('drivers');

it('paginates with length-aware metadata', function (string $driver): void {
    useStorageDriver($driver);

    foreach (range(1, 5) as $i) {
        recordTrace(makeTrace(name: "t{$i}", startedAt: new DateTimeImmutable("2026-01-01 12:00:0{$i}")));
    }

    $page = app(TraceReader::class)->paginate(TraceQuery::new(), perPage: 2, page: 2);

    expect($page)->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($page->total())->toBe(5)
        ->and($page->perPage())->toBe(2)
        ->and($page->currentPage())->toBe(2)
        ->and($page->lastPage())->toBe(3)
        ->and($page->count())->toBe(2)
        ->and($page->collect()->first())->toBeInstanceOf(Trace::class);
})->with('drivers');

it('counts traces matching a query without materialising them', function (string $driver): void {
    useStorageDriver($driver);

    recordTrace(makeTrace(name: 'http.request'));
    recordTrace(makeTrace(name: 'http.request'));
    recordTrace(makeTrace(name: 'queue.job'));

    expect(app(TraceReader::class)->count(TraceQuery::new()->whereName('http.request')))->toBe(2)
        ->and(app(TraceReader::class)->count(TraceQuery::new()))->toBe(3);
})->with('drivers');

it('round-trips status, timestamps and error details for a failed trace', function (string $driver): void {
    useStorageDriver($driver);

    $recorded = recordTrace(makeTrace(
        name: 'checkout',
        status: TraceStatus::Failed,
        startedAt: new DateTimeImmutable('2026-01-01 12:00:00.500000'),
        durationMs: 40,
        withError: true,
    ));

    $found = app(TraceReader::class)->find($recorded->id);

    expect($found?->status)->toBe(TraceStatus::Failed)
        ->and($found?->startedAt->format('Y-m-d\TH:i:s.u'))->toBe('2026-01-01T12:00:00.500000')
        ->and($found?->finishedAt)->not->toBeNull()
        ->and($found?->error?->type)->toBe(RuntimeException::class)
        ->and($found?->error?->message)->toBe('boom');
})->with('drivers');
