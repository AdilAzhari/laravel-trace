<?php

declare(strict_types=1);

use AdilAzhari\LaravelTrace\Trace\Trace;
use AdilAzhari\LaravelTrace\Trace\TraceId;
use AdilAzhari\LaravelTrace\Trace\TraceStatus;

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
        ->and($failed->error?->type)
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

it('preserves attributes when completing a trace', function (): void {
    $startedAt = new DateTimeImmutable('2026-01-01 10:00:00');
    $finishedAt = new DateTimeImmutable('2026-01-01 10:00:01');

    $trace = new Trace(
        id: TraceId::generate(),
        name: 'http.request',
        status: TraceStatus::Running,
        startedAt: $startedAt,
        attributes: [
            'http.method' => 'POST',
        ],
    );

    $completed = $trace->complete($finishedAt, [
        'http.status_code' => 201,
    ]);

    expect($completed->attributes)
        ->toBe([
            'http.method' => 'POST',
            'http.status_code' => 201,
        ]);
});

it('preserves attributes when failing a trace', function (): void {
    $startedAt = new DateTimeImmutable('2026-01-01 10:00:00');
    $finishedAt = new DateTimeImmutable('2026-01-01 10:00:01');

    $trace = new Trace(
        id: TraceId::generate(),
        name: 'http.request',
        status: TraceStatus::Running,
        startedAt: $startedAt,
        attributes: [
            'http.method' => 'POST',
        ],
    );

    $failed = $trace->fail(
        new RuntimeException('Something failed.'),
        $finishedAt,
        [
            'http.status_code' => 500,
        ],
    );

    expect($failed->attributes)
        ->toBe([
            'http.method' => 'POST',
            'http.status_code' => 500,
        ]);
});
