<?php

declare(strict_types=1);

use AdilAzhari\LaravelTrace\Context\TraceContext;
use AdilAzhari\LaravelTrace\Contracts\SpanReader;
use AdilAzhari\LaravelTrace\Contracts\TraceContextStore;
use AdilAzhari\LaravelTrace\Contracts\TraceReader;
use AdilAzhari\LaravelTrace\Models\TraceRecord;
use AdilAzhari\LaravelTrace\Read\SpanQuery;
use AdilAzhari\LaravelTrace\Read\TraceQuery;
use AdilAzhari\LaravelTrace\Trace\TraceId;
use AdilAzhari\LaravelTrace\Tracing\InMemorySpanStore;
use AdilAzhari\LaravelTrace\Tracing\InMemoryStorageCleaner;
use AdilAzhari\LaravelTrace\Tracing\InMemoryTraceStore;

it('resolves as a singleton from the container', function (): void {
    expect(app(InMemoryStorageCleaner::class))
        ->toBe(app(InMemoryStorageCleaner::class));
});

it('clears the exact store instances shared with the recorder and reader, not private copies', function (): void {
    // Populated directly through the container-resolved stores, never
    // through the cleaner itself, so a passing assertion here can only mean
    // the cleaner was constructed with these same singleton instances.
    app(InMemoryTraceStore::class)->put(makeTrace());
    app(InMemorySpanStore::class)->put(makeSpan(TraceId::generate()));

    app(InMemoryStorageCleaner::class)->clear();

    expect(app(InMemoryTraceStore::class)->all())->toBe([])
        ->and(app(InMemorySpanStore::class)->all())->toBe([]);
});

it('does not touch the active trace context when clearing memory storage', function (): void {
    $context = new TraceContext(TraceId::generate());
    app(TraceContextStore::class)->set($context);

    $trace = recordTrace(makeTrace());
    recordSpan(makeSpan($trace->id));

    app(InMemoryStorageCleaner::class)->clear();

    expect(app(TraceContextStore::class)->get())->toBe($context);
});

it('makes the memory reader stop returning traces and spans once cleared', function (): void {
    $trace = recordTrace(makeTrace());
    recordSpan(makeSpan($trace->id));

    expect(app(TraceReader::class)->find($trace->id))->not->toBeNull();

    app(InMemoryStorageCleaner::class)->clear();

    expect(app(TraceReader::class)->find($trace->id))->toBeNull()
        ->and(app(TraceReader::class)->get(new TraceQuery))->toHaveCount(0)
        ->and(app(SpanReader::class)->get(new SpanQuery))->toHaveCount(0);
});

it('does not delete database-driver records when the memory cleaner runs', function (): void {
    useStorageDriver('database');

    $trace = recordTrace(makeTrace());
    recordSpan(makeSpan($trace->id));

    app(InMemoryStorageCleaner::class)->clear();

    expect(TraceRecord::query()->find($trace->id->value))->not->toBeNull()
        ->and(app(TraceReader::class)->find($trace->id))->not->toBeNull();
});
