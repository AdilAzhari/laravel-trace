<?php

declare(strict_types=1);

use AdilAzhari\LaravelTrace\Facades\LaravelTrace;
use AdilAzhari\LaravelTrace\Http\Middleware\TraceRequest;
use AdilAzhari\LaravelTrace\Read\SpanNode;
use AdilAzhari\LaravelTrace\Span\Span;
use AdilAzhari\LaravelTrace\Span\SpanType;
use AdilAzhari\LaravelTrace\Trace\Trace;
use AdilAzhari\LaravelTrace\Trace\TraceId;
use AdilAzhari\LaravelTrace\Trace\TraceStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

it('reads back a trace and its span tree recorded by a real request', function (): void {
    config()->set('laravel-trace.storage.driver', 'database');

    Route::middleware(TraceRequest::class)->get('/read-api-test', function () {
        DB::connection()->select('select 1');

        return response()->json(['ok' => true]);
    });

    $this->get('/read-api-test')->assertSuccessful();

    $trace = LaravelTrace::traces()->whereName('http.request')->first();

    expect($trace)->toBeInstanceOf(Trace::class)
        ->and($trace?->status)->toBe(TraceStatus::Completed);

    $spans = LaravelTrace::spansForTrace($trace->id);

    expect($spans->map(fn (Span $s): string => $s->type->value)->sort()->values()->all())
        ->toBe(['database', 'http']);

    $tree = LaravelTrace::spanTree($trace->id);

    expect($tree)->toHaveCount(1)
        ->and($tree[0])->toBeInstanceOf(SpanNode::class)
        ->and($tree[0]->span->type)->toBe(SpanType::Http)
        ->and($tree[0]->children)->toHaveCount(1)
        ->and($tree[0]->children[0]->span->type)->toBe(SpanType::Database);
});

it('looks up a single trace and span by id through the facade', function (): void {
    config()->set('laravel-trace.storage.driver', 'database');

    $traceId = TraceId::generate();
    recordTrace(makeTrace(id: $traceId, name: 'checkout'));
    $span = recordSpan(makeSpan($traceId, 'reserve-inventory'));

    expect(LaravelTrace::trace($traceId->value)?->name)->toBe('checkout')
        ->and(LaravelTrace::trace($traceId)?->name)->toBe('checkout')
        ->and(LaravelTrace::span($span->id->value)?->name)->toBe('reserve-inventory')
        ->and(LaravelTrace::trace('01UNKNOWNUNKNOWNUNKNOWN01'))->toBeNull();
});

it('does not record spans for its own read queries while a trace is active', function (): void {
    config()->set('laravel-trace.storage.driver', 'database');

    Route::middleware(TraceRequest::class)->get('/read-api-noninstrumented', function () {
        // A read issued from inside a traced request must not appear as
        // database spans on that trace.
        LaravelTrace::traces()->get();
        LaravelTrace::traces()->count();

        return response()->json(['ok' => true]);
    });

    $this->get('/read-api-noninstrumented')->assertSuccessful();

    $trace = LaravelTrace::traces()->whereName('http.request')->first();
    $spans = LaravelTrace::spansForTrace($trace->id);

    expect($spans->map(fn (Span $s): string => $s->type->value)->all())->toBe(['http']);
});

it('exposes the same read api when the memory driver is active', function (): void {
    // Default driver is memory.
    $traceId = TraceId::generate();
    recordTrace(makeTrace(id: $traceId, name: 'checkout', status: TraceStatus::Failed, withError: true));
    recordSpan(makeSpan($traceId, 'charge', SpanType::Action));

    expect(LaravelTrace::traces()->onlyErrors()->get())->toHaveCount(1)
        ->and(LaravelTrace::trace($traceId)?->status)->toBe(TraceStatus::Failed)
        ->and(LaravelTrace::spansForTrace($traceId)->first())->toBeInstanceOf(Span::class);
});
