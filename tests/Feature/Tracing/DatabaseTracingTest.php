<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use LaravelTrace\LaravelTrace\Contracts\Tracer;
use LaravelTrace\LaravelTrace\Span\SpanType;
use LaravelTrace\LaravelTrace\Tracing\InMemorySpanRecorder;

it('records database queries inside an active trace', function (): void {
    $tracer = app(Tracer::class);

    $tracer->start('DatabaseTest');

    DB::select('select 1');

    $spans = app(InMemorySpanRecorder::class)->all();

    expect($spans)
        ->toHaveCount(1)
        ->and($spans[0]->name)
        ->toBe('database.query')
        ->and($spans[0]->type)
        ->toBe(SpanType::Database);
});
