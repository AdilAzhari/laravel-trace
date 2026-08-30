<?php

declare(strict_types=1);

use AdilAzhari\LaravelTrace\Contracts\Tracer;
use AdilAzhari\LaravelTrace\Http\Middleware\TraceRequest;
use AdilAzhari\LaravelTrace\Span\SpanType;
use AdilAzhari\LaravelTrace\Tracing\InMemorySpanRecorder;
use AdilAzhari\LaravelTrace\Tracing\InMemoryTraceRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

it('records database queries during an http request', function (): void {
    Route::middleware(TraceRequest::class)
        ->get('/trace-database-test', function () {
            DB::select('select 1');

            return response()->json(['ok' => true]);
        });

    $response = $this->get('/trace-database-test');

    $response->assertSuccessful();

    $spans = app(InMemorySpanRecorder::class)->all();

    $databaseSpan = collect($spans)
        ->firstWhere('type', SpanType::Database);

    expect($databaseSpan)
        ->not->toBeNull()
        ->and($databaseSpan->name)
        ->toBe('database.query')
        ->and($databaseSpan->attributes)
        ->toMatchArray([
            'db.connection' => 'testing',
            'db.sql' => 'select 1',
        ]);

    $trace = collect(app(InMemoryTraceRecorder::class)->all())
        ->last();

    expect($trace)
        ->not->toBeNull()
        ->and($databaseSpan->traceId->value)
        ->toBe($trace->id->value);
});

it('records database query metadata inside an active trace', function (): void {
    $tracer = app(Tracer::class);

    $tracer->start('DatabaseTest', []);

    DB::select('select 1');

    $spans = app(InMemorySpanRecorder::class)->all();

    $span = collect($spans)->firstWhere('type', SpanType::Database);

    expect($span)
        ->not->toBeNull()
        ->and($span->attributes)
        ->toMatchArray([
            'db.connection' => 'testing',
            'db.sql' => 'select 1',
        ])
        ->and($span->name)
        ->toBe('database.query')
        ->and($span->type)
        ->toBe(SpanType::Database)
        ->and($span->attributes)
        ->toHaveKey('db.connection')
        ->and($span->attributes['db.connection'])
        ->toBe('testing')
        ->and($span->attributes)
        ->toHaveKey('db.duration_ms')
        ->and($span->attributes['db.duration_ms'])
        ->toBeFloat()
        ->toBeFloat();
});

it('does not record database queries when database tracing is disabled', function (): void {
    config()->set(
        'laravel-trace.database.enabled',
        false,
    );

    $tracer = app(Tracer::class);

    $tracer->start('DatabaseTest', []);

    DB::select('select 1');

    $spans = app(InMemorySpanRecorder::class)->all();

    expect(collect($spans)->firstWhere('type', SpanType::Database))
        ->toBeNull();
});

it('does not trace database queries when tracing is globally disabled', function (): void {
    config()->set('laravel-trace.enabled', false);

    $tracer = app(Tracer::class);

    $tracer->start('DatabaseTest', []);

    DB::select('select 1');

    $spans = app(InMemorySpanRecorder::class)->all();

    expect(collect($spans)->firstWhere('type', SpanType::Database))
        ->toBeNull();
});
