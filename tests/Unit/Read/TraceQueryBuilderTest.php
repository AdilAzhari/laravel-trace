<?php

declare(strict_types=1);

use AdilAzhari\LaravelTrace\Read\InMemoryTraceReader;
use AdilAzhari\LaravelTrace\Read\TraceQueryBuilder;
use AdilAzhari\LaravelTrace\Trace\TraceStatus;
use AdilAzhari\LaravelTrace\Tracing\InMemoryTraceStore;
use Illuminate\Pagination\LengthAwarePaginator;

function traceBuilder(?InMemoryTraceStore $store = null): TraceQueryBuilder
{
    return new TraceQueryBuilder(new InMemoryTraceReader($store ?? new InMemoryTraceStore));
}

it('accumulates fluent filters into the underlying immutable query', function (): void {
    $query = traceBuilder()
        ->whereName('http.request')
        ->whereStatus(TraceStatus::Failed)
        ->onlyErrors()
        ->orderBy('duration_ms', 'asc')
        ->toQuery();

    expect($query->names)->toBe(['http.request'])
        ->and($query->statuses)->toBe([TraceStatus::Failed])
        ->and($query->hasError)->toBeTrue()
        ->and($query->orderBy->field)->toBe('duration_ms')
        ->and($query->orderBy->direction)->toBe('asc');
});

it('delegates the terminal calls to the reader', function (): void {
    $store = new InMemoryTraceStore;
    $store->put(makeTrace('http.request'));
    $store->put(makeTrace('http.request'));
    $store->put(makeTrace('queue.job'));

    expect(traceBuilder($store)->whereName('http.request')->get())->toHaveCount(2)
        ->and(traceBuilder($store)->count())->toBe(3)
        ->and(traceBuilder($store)->whereName('queue.job')->first()?->name)->toBe('queue.job')
        ->and(traceBuilder($store)->whereName('missing')->exists())->toBeFalse()
        ->and(traceBuilder($store)->paginate(perPage: 2))->toBeInstanceOf(LengthAwarePaginator::class)
        ->and(traceBuilder($store)->paginate(perPage: 2)->total())->toBe(3);
});
