<?php

declare(strict_types=1);

use AdilAzhari\LaravelTrace\Http\Middleware\TraceRequest;
use AdilAzhari\LaravelTrace\Models\SpanRecord;
use AdilAzhari\LaravelTrace\Models\TraceRecord;
use AdilAzhari\LaravelTrace\Span\SpanStatus;
use AdilAzhari\LaravelTrace\Trace\TraceStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

it('persists a completed trace and its spans for a traced http request', function (): void {
    config()->set('laravel-trace.storage.driver', 'database');

    Route::middleware(TraceRequest::class)
        ->get('/trace-database-persistence-test', function () {
            DB::connection()->select('select 1');

            return response()->json(['ok' => true]);
        });

    $this->get('/trace-database-persistence-test')->assertSuccessful();

    $trace = TraceRecord::query()->first();

    expect($trace)->not->toBeNull()
        ->and($trace->status)->toBe(TraceStatus::Completed->value)
        ->and($trace->name)->toBe('http.request');

    $spans = SpanRecord::query()->where('trace_id', $trace->id)->get();

    // The http.request span and the database.query span it wrapped - and,
    // critically, nothing more: without the recursion guard, the recorder's
    // own writes to laravel_traces/laravel_trace_spans would themselves be
    // instrumented as further `database` spans without end.
    expect($spans)->toHaveCount(2)
        ->and($spans->pluck('type')->sort()->values()->all())
        ->toBe(['database', 'http']);
});

it('fails the trace and its span for a traced http request that throws', function (): void {
    config()->set('laravel-trace.storage.driver', 'database');

    Route::middleware(TraceRequest::class)
        ->get('/trace-database-persistence-failure-test', function (): void {
            throw new RuntimeException('Something failed.');
        });

    $this->withoutExceptionHandling();

    expect(fn () => $this->get('/trace-database-persistence-failure-test'))
        ->toThrow(RuntimeException::class, 'Something failed.');

    $trace = TraceRecord::query()->first();

    expect($trace->status)->toBe(TraceStatus::Failed->value)
        ->and($trace->error_type)->toBe(RuntimeException::class);

    $span = SpanRecord::query()->where('trace_id', $trace->id)->first();

    expect($span->status)->toBe(SpanStatus::Failed->value);
});
