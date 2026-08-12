<?php

declare(strict_types=1);

use LaravelTrace\LaravelTrace\Trace\Trace;
use LaravelTrace\LaravelTrace\Trace\TraceStatus;

it('starts a trace', function () {
    $trace = Trace::start('CreateOrder');

    expect($trace->name)
        ->toBe('CreateOrder')
        ->and($trace->status)
        ->toBe(TraceStatus::Running)
        ->and($trace->finishedAt)
        ->toBeNull();
});

it('generates a trace id when starting', function () {
    $trace = Trace::start('CreateOrder');

    expect($trace->id->value)
        ->not->toBeEmpty();
});
