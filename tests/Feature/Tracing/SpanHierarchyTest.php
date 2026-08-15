<?php

declare(strict_types=1);

use LaravelTrace\LaravelTrace\Contracts\SpanRecorder;
use LaravelTrace\LaravelTrace\Tracing\InMemorySpanRecorder;

// it('maintains span parent relationships during an http request', function () {
//    $response = $this->postJson('/trace-test/nested');
//
//    $response->assertSuccessful();
//
//    $spans = app(InMemorySpanRecorder::class)->all();
//
//    expect($spans)
//        ->toHaveCount(3);
//
//    [$parent, $database, $inventory] = $spans;
//
//    expect($parent->parentId)
//        ->toBeNull()
//        ->and($database->parentId)
//        ->toBe($parent->id)
//        ->and($inventory->parentId)
//        ->toBe($parent->id);
// });
//
// it('maintains deeply nested span relationships', function () {
//    $this->postJson('/trace-test/deep')
//        ->assertSuccessful();
//
//    $spans = app(InMemorySpanRecorder::class)->all();
//
//    expect($spans)
//        ->toHaveCount(3);
//
//    [$outer, $middle, $inner] = $spans;
//
//    expect($outer->parentId)
//        ->toBeNull()
//        ->and($middle->parentId)
//        ->toBe($outer->id)
//        ->and($inner->parentId)
//        ->toBe($middle->id);
// });
// it('uses the same span recorder instance', function () {
//    expect(app(SpanRecorder::class))
//        ->toBe(app(InMemorySpanRecorder::class));
// });

// use LaravelTrace\LaravelTrace\Contracts\SpanRecorder;
// use LaravelTrace\LaravelTrace\Tracing\InMemorySpanRecorder;

it('uses the same span recorder instance', function (): void {
    expect(app(SpanRecorder::class))
        ->toBe(app(InMemorySpanRecorder::class));
});
