<?php

declare(strict_types=1);

use AdilAzhari\LaravelTrace\Contracts\Tracer;
use AdilAzhari\LaravelTrace\Span\SpanType;
use AdilAzhari\LaravelTrace\Tracing\InMemorySpanRecorder;

it('maintains nested span parent relationships', function (): void {
    $tracer = app(Tracer::class);

    $tracer->start('test');

    $outer = $tracer->span(
        name: 'outer',
        type: SpanType::Database,
    );

    $middle = $tracer->span(
        name: 'middle',
        type: SpanType::Database,
    );

    $inner = $tracer->span(
        name: 'inner',
        type: SpanType::Database,
    );

    expect($outer->span()->parentId)
        ->toBeNull()
        ->and($middle->span()->parentId)
        ->toBe($outer->span()->id)
        ->and($inner->span()->parentId)
        ->toBe($middle->span()->id);

    $inner->close();

    expect($tracer->context()?->spanId)
        ->toBe($middle->span()->id);

    $middle->close();

    expect($tracer->context()?->spanId)
        ->toBe($outer->span()->id);

    $outer->close();

    expect($tracer->context()?->spanId)
        ->toBeNull();
});

it('records nested spans with their correct parent relationships', function (): void {
    $tracer = app(Tracer::class);

    $tracer->start('test');

    $outer = $tracer->span(
        name: 'outer',
        type: SpanType::Database,
    );

    $inner = $tracer->span(
        name: 'inner',
        type: SpanType::Database,
    );

    $inner->close();
    $outer->close();

    $spans = app(InMemorySpanRecorder::class)->all();

    $outerSpan = collect($spans)
        ->firstWhere('name', 'outer');

    $innerSpan = collect($spans)
        ->firstWhere('name', 'inner');

    expect($spans)
        ->toHaveCount(2)
        ->and($outerSpan)
        ->not->toBeNull()
        ->and($innerSpan)
        ->not->toBeNull()
        ->and($outerSpan->parentId)
        ->toBeNull()
        ->and($innerSpan->parentId)
        ->toBe($outerSpan->id);
});
