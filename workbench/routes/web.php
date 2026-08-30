<?php

declare(strict_types=1);

use AdilAzhari\LaravelTrace\Contracts\Tracer;
use AdilAzhari\LaravelTrace\Http\Middleware\TraceRequest;
use AdilAzhari\LaravelTrace\Span\SpanType;
use Illuminate\Support\Facades\Route;

Route::post('/trace-test/nested', function () {
    $tracer = app(Tracer::class);

    $parent = $tracer->span(
        'business.operation',
        SpanType::Action,
    );

    $event = $tracer->span(
        'order.created',
        SpanType::Event,
    );

    $event->close();

    $listener = $tracer->span(
        'inventory.reserve',
        SpanType::Listener,
    );

    $listener->close();

    $parent->close();

    return response()->json([
        'ok' => true,
    ]);
})->middleware(TraceRequest::class);

Route::post('/trace-test', function () {
    return response()->json([
        'ok' => true,
    ]);
})->middleware(TraceRequest::class);

Route::post('/trace-test-failure', function (): void {
    throw new RuntimeException('Something failed.');
})->middleware(TraceRequest::class);

Route::post('/trace-test/deep', function () {
    $tracer = app(Tracer::class);

    $outer = $tracer->span(
        'outer',
        SpanType::Action,
    );

    $middle = $tracer->span(
        'middle',
        SpanType::Action,
    );

    $inner = $tracer->span(
        'inner',
        SpanType::Action,
    );

    $inner->close();
    $middle->close();
    $outer->close();

    return response()->json([
        'ok' => true,
    ]);
})->middleware(TraceRequest::class);
