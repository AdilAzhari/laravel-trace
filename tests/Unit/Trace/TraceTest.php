<?php

declare(strict_types=1);

use LaravelTrace\LaravelTrace\Trace\Trace;
use LaravelTrace\LaravelTrace\Trace\TraceStatus;

it('starts a trace', function (): void {
    $trace = Trace::start('CreateOrder');

    expect($trace->name)
        ->toBe('CreateOrder')
        ->and($trace->status)
        ->toBe(TraceStatus::Running)
        ->and($trace->finishedAt)
        ->toBeNull();
});

it('generates a trace id when starting', function (): void {
    $trace = Trace::start('CreateOrder');

    expect($trace->id->value)
        ->not->toBeEmpty();
});

it('completes a trace', function (): void {
    $trace = Trace::start('CreateOrder');

    $finishedAt = new DateTimeImmutable;

    $completed = $trace->complete($finishedAt);

    expect($completed)
        ->not->toBe($trace)
        ->and($completed->id)
        ->toBe($trace->id)
        ->and($completed->name)
        ->toBe('CreateOrder')
        ->and($completed->status)
        ->toBe(TraceStatus::Completed)
        ->and($completed->startedAt)
        ->toBe($trace->startedAt)
        ->and($completed->finishedAt)
        ->toBe($finishedAt)
        ->and($completed->error)
        ->toBeNull();
});

it('fails a trace', function (): void {
    $trace = Trace::start('CreateOrder');

    $exception = new RuntimeException('Something failed.');
    $finishedAt = new DateTimeImmutable;

    $failed = $trace->fail(
        exception: $exception,
        finishedAt: $finishedAt,
    );

    expect($failed)
        ->not->toBe($trace)
        ->and($failed->status)
        ->toBe(TraceStatus::Failed)
        ->and($failed->finishedAt)
        ->toBe($finishedAt)
        ->and($failed->error)
        ->not->toBeNull()
        ->and($failed->error?->class)
        ->toBe(RuntimeException::class)
        ->and($failed->error?->message)
        ->toBe('Something failed.');
});

it('starts a trace with attributes', function (): void {
    $trace = Trace::start(
        name: 'http.request',
        attributes: [
            'http.method' => 'GET',
            'http.path' => '/users',
        ],
    );

    expect($trace->attributes)
        ->toMatchArray([
            'http.method' => 'GET',
            'http.path' => '/users',
        ]);
});

it('can add attributes without mutating the original trace', function (): void {
    $trace = Trace::start(
        name: 'http.request',
        attributes: [
            'http.method' => 'GET',
        ],
    );

    $updated = $trace->withAttributes([
        'http.status_code' => 200,
    ]);

    expect($trace->attributes)
        ->toBe([
            'http.method' => 'GET',
        ])
        ->and($updated->attributes)
        ->toBe([
            'http.method' => 'GET',
            'http.status_code' => 200,
        ]);
});
