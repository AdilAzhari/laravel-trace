<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use LaravelTrace\LaravelTrace\Contracts\Tracer;
use LaravelTrace\LaravelTrace\Span\SpanType;
use LaravelTrace\LaravelTrace\Tracing\InMemorySpanRecorder;

it('records database query metadata inside an active trace', function (): void {
    $tracer = app(Tracer::class);

    $tracer->start('DatabaseTest');

    DB::select('select 1');

    $spans = app(InMemorySpanRecorder::class)->all();

    expect($spans)
        ->toHaveCount(1);

    $span = $spans[0];

    expect($span->attributes)
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

    $tracer->start('DatabaseTest');

    DB::select('select 1');

    expect(app(InMemorySpanRecorder::class)->all())
        ->toBeEmpty();
});
