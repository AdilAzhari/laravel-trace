<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use LaravelTrace\LaravelTrace\Contracts\Tracer;
use LaravelTrace\LaravelTrace\Http\Middleware\TraceRequest;
use LaravelTrace\LaravelTrace\Span\SpanType;

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
    $tracer = app(Tracer::class);

    $scope = $tracer->span(
        'business.operation',
        SpanType::Action,
    );

    $scope->close();

    return response()->json([
        'ok' => true,
    ]);
})->middleware(TraceRequest::class);

Route::post('/trace-test-failure', function (): void {
    $tracer = app(Tracer::class);

    $scope = $tracer->span(
        'business.operation',
        SpanType::Action,
    );

    try {
        throw new RuntimeException('Something failed.');
    } catch (Throwable $exception) {
        $scope->fail($exception);

        throw $exception;
    }
})->middleware(TraceRequest::class);

Route::post('/trace-test-failure', function (): void {
    $tracer = app(Tracer::class);

    expect($tracer->context())
        ->not->toBeNull();

    $scope = $tracer->span(
        'business.operation',
        SpanType::Action,
    );

    try {
        throw new RuntimeException('Something failed.');
    } catch (Throwable $exception) {
        $scope->fail($exception);

        throw $exception;
    }
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
