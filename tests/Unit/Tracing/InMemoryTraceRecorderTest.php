<?php

declare(strict_types=1);

use LaravelTrace\LaravelTrace\Trace\Trace;
use LaravelTrace\LaravelTrace\Tracing\InMemoryTraceRecorder;

it('records a trace', function (): void {
    $recorder = new InMemoryTraceRecorder;

    $trace = Trace::start('Test');

    $recorder->record($trace);

    expect($recorder->all())
        ->toHaveCount(1)
        ->and($recorder->all()[0]->id)
        ->toBe($trace->id);
});

it('records multiple traces', function (): void {
    $recorder = new InMemoryTraceRecorder;

    $first = Trace::start('First');
    $second = Trace::start('Second');

    $recorder->record($first);
    $recorder->record($second);

    expect($recorder->all())
        ->toHaveCount(2);
});
