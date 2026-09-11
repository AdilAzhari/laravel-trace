<?php

declare(strict_types=1);

use AdilAzhari\LaravelTrace\Contracts\SpanReader;
use AdilAzhari\LaravelTrace\Contracts\TraceReader;
use AdilAzhari\LaravelTrace\Read\DatabaseTraceReader;
use AdilAzhari\LaravelTrace\Read\InMemorySpanReader;
use AdilAzhari\LaravelTrace\Read\InMemoryTraceReader;
use AdilAzhari\LaravelTrace\Read\StorageDrivenSpanReader;
use AdilAzhari\LaravelTrace\Read\StorageDrivenTraceReader;
use AdilAzhari\LaravelTrace\Read\TraceQuery;
use AdilAzhari\LaravelTrace\Trace\TraceId;

// The reader contracts resolve to the same live-switching wrapper whatever
// the driver; which concrete reader it forwards to is decided per call from
// `laravel-trace.storage.driver`. These assertions are behavioural (which
// store did the read hit?) rather than about the resolved type, mirroring
// StorageDriverResolutionTest on the write side.

it('binds the reader contracts to the storage-driven wrappers', function (): void {
    expect(app(TraceReader::class))->toBeInstanceOf(StorageDrivenTraceReader::class)
        ->and(app(SpanReader::class))->toBeInstanceOf(StorageDrivenSpanReader::class);
});

it('registers each reader contract exactly once', function (): void {
    // A second resolution must return the same singleton instance, proving
    // there is no accidental duplicate binding shadowing the first.
    expect(app(TraceReader::class))->toBe(app(TraceReader::class))
        ->and(app(SpanReader::class))->toBe(app(SpanReader::class));
});

it('routes reads to the memory driver by default', function (): void {
    useStorageDriver('memory');

    recordTrace(makeTrace('checkout'));

    expect(app(TraceReader::class)->get(TraceQuery::new()))->toHaveCount(1)
        ->and(app(InMemoryTraceReader::class)->get(TraceQuery::new()))->toHaveCount(1);
});

it('routes reads to the database driver when configured', function (): void {
    useStorageDriver('database');

    recordTrace(makeTrace('checkout'));

    expect(app(TraceReader::class)->get(TraceQuery::new()))->toHaveCount(1)
        ->and(app(DatabaseTraceReader::class)->get(TraceQuery::new()))->toHaveCount(1)
        ->and(app(InMemoryTraceReader::class)->get(TraceQuery::new()))->toHaveCount(0);
});

it('throws for an unknown storage driver when reading traces', function (): void {
    config()->set('laravel-trace.storage.driver', 'elasticsearch');

    expect(fn () => app(TraceReader::class)->get(TraceQuery::new()))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(TraceReader::class)->find(TraceId::generate()))
        ->toThrow(InvalidArgumentException::class);
});

it('throws for an unknown storage driver when reading spans', function (): void {
    config()->set('laravel-trace.storage.driver', 'elasticsearch');

    expect(fn () => app(SpanReader::class)->forTrace(TraceId::generate()))
        ->toThrow(InvalidArgumentException::class);
});

it('keeps the memory reader and recorder pointed at one shared store', function (): void {
    expect(app(InMemoryTraceReader::class))->toBeInstanceOf(InMemoryTraceReader::class)
        ->and(app(InMemorySpanReader::class))->toBeInstanceOf(InMemorySpanReader::class);

    useStorageDriver('memory');
    $trace = recordTrace(makeTrace('checkout'));

    // Written through the recorder, visible through the reader: same store.
    expect(app(TraceReader::class)->find($trace->id))->not->toBeNull();
});
