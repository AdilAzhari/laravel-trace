<?php

declare(strict_types=1);

use AdilAzhari\LaravelTrace\Http\Middleware\TraceRequest;
use AdilAzhari\LaravelTrace\Span\Span;
use AdilAzhari\LaravelTrace\Span\SpanType;
use AdilAzhari\LaravelTrace\Storage\DatabaseSpanRecorder;
use AdilAzhari\LaravelTrace\Storage\DatabaseTraceRecorder;
use AdilAzhari\LaravelTrace\Trace\Trace;
use AdilAzhari\LaravelTrace\Trace\TraceId;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

it('keeps a traced http request working when the trace table is missing', function (): void {
    config()->set('laravel-trace.storage.driver', 'database');

    Log::shouldReceive('warning')->atLeast()->once();

    // Drop the tables the database recorder writes to, simulating a
    // misconfigured or not-yet-migrated storage setup.
    Schema::dropIfExists('laravel_trace_spans');
    Schema::dropIfExists('laravel_traces');

    Route::middleware(TraceRequest::class)
        ->get('/trace-storage-failure-test', fn () => response()->json(['ok' => true]));

    $this->get('/trace-storage-failure-test')->assertSuccessful();
});

it('rethrows storage exceptions when swallow_exceptions is disabled', function (): void {
    config()->set('laravel-trace.storage.driver', 'database');
    config()->set('laravel-trace.storage.database.swallow_exceptions', false);

    Schema::dropIfExists('laravel_trace_spans');
    Schema::dropIfExists('laravel_traces');

    $recorder = app(DatabaseTraceRecorder::class);

    expect(fn () => $recorder->record(Trace::start('CreateOrder')))
        ->toThrow(QueryException::class);
});

it('stops attempting further writes for the rest of the request after the first failure', function (): void {
    config()->set('laravel-trace.storage.driver', 'database');

    Schema::dropIfExists('laravel_trace_spans');
    Schema::dropIfExists('laravel_traces');

    $recorder = app(DatabaseSpanRecorder::class);

    DB::enableQueryLog();

    // Fails against the missing table and trips the circuit breaker.
    $recorder->record(Span::start(TraceId::generate(), 'first', SpanType::Action));

    $queriesAfterFirstFailure = count(DB::getQueryLog());

    // The recorder is disabled for the rest of the request: this call must
    // not attempt another query at all, not merely fail silently again.
    $recorder->record(Span::start(TraceId::generate(), 'second', SpanType::Action));

    expect(count(DB::getQueryLog()))->toBe($queriesAfterFirstFailure);
});
