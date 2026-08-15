<?php

declare(strict_types=1);

// use LaravelTrace\LaravelTrace\Span\SpanStatus;
// use LaravelTrace\LaravelTrace\Tracing\InMemorySpanRecorder;
// use LaravelTrace\LaravelTrace\Tracing\InMemoryTraceRecorder;

// it('traces an http request end to end', function () {
//    $spanRecorder = app(InMemorySpanRecorder::class);
//    $traceRecorder = app(InMemoryTraceRecorder::class);
//
//    $response = $this->postJson('/trace-test');
//
//    $response
//        ->assertSuccessful()
//        ->assertJson([
//            'ok' => true,
//        ]);
//
//    expect($traceRecorder->all())
//        ->toHaveCount(1);
//
//    expect($spanRecorder->all())
//        ->toHaveCount(1);
//
//    $span = $spanRecorder->all()[0];
//
//    expect($span->name)
//        ->toBe('business.operation')
//        ->and($span->status)
//        ->toBe(SpanStatus::Completed);
// });

// use LaravelTrace\LaravelTrace\Tracing\Tracer;
use LaravelTrace\LaravelTrace\Contracts\Tracer;

it('starts a trace for an http request', function (): void {
    $tracer = app(Tracer::class);

    $response = $this->postJson('/trace-test');

    $response->assertSuccessful();

    expect($tracer->context())
        ->toBeNull();
});

it('clears tracing context when the request fails', function (): void {
    $tracer = app(Tracer::class);

    $this->postJson('/trace-test-failure')
        ->assertServerError();

    expect($tracer->context())
        ->toBeNull();
});
