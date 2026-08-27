<?php

declare(strict_types=1);

use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Support\Facades\Queue;
use LaravelTrace\LaravelTrace\Contracts\Tracer;
use LaravelTrace\LaravelTrace\Span\SpanStatus;
use LaravelTrace\LaravelTrace\Span\SpanType;
use LaravelTrace\LaravelTrace\Tests\Fixtures\Jobs\FailingJob;
use LaravelTrace\LaravelTrace\Tests\Fixtures\Jobs\TracedJob;
use LaravelTrace\LaravelTrace\Tracing\InMemorySpanRecorder;

it('records a span when a queued job is processed', function (): void {
    $tracer = app(Tracer::class);

    $tracer->start('QueueTest');

    app(QueueFactory::class)->connection('sync')->push(new TracedJob);

    $span = collect(app(InMemorySpanRecorder::class)->all())
        ->firstWhere('name', 'queue.job');

    expect($span)
        ->not->toBeNull()
        ->and($span->type)
        ->toBe(SpanType::Job)
        ->and($span->status)
        ->toBe(SpanStatus::Completed)
        ->and($span->attributes)
        ->toMatchArray([
            'queue.connection' => 'sync',
            'queue.job' => TracedJob::class,
        ]);
});

it('fails the job span when the job throws', function (): void {
    $tracer = app(Tracer::class);

    $tracer->start('QueueTest');

    expect(fn () => app(QueueFactory::class)->connection('sync')->push(new FailingJob))
        ->toThrow(RuntimeException::class, 'Job failed.');

    $span = collect(app(InMemorySpanRecorder::class)->all())
        ->firstWhere('name', 'queue.job');

    expect($span)
        ->not->toBeNull()
        ->and($span->status)
        ->toBe(SpanStatus::Failed)
        ->and($span->error?->type)
        ->toBe(RuntimeException::class);
});

it('does not record job spans when queue tracing is disabled', function (): void {
    config()->set('laravel-trace.queue.enabled', false);

    $tracer = app(Tracer::class);

    $tracer->start('QueueTest');

    app(QueueFactory::class)->connection('sync')->push(new TracedJob);

    expect(collect(app(InMemorySpanRecorder::class)->all())->firstWhere('name', 'queue.job'))
        ->toBeNull();
});

it('does not record job spans when there is no active trace', function (): void {
    app(QueueFactory::class)->connection('sync')->push(new TracedJob);

    expect(app(InMemorySpanRecorder::class)->all())
        ->toBeEmpty();
});

it('includes trace context in the queued job payload', function (): void {
    $tracer = app(Tracer::class);

    $trace = $tracer->start('QueueTest');

    $parent = $tracer->span(
        name: 'dispatch.job',
        type: SpanType::Action,
    );

    $payload = null;

    Queue::createPayloadUsing(
        function ($connection, $queue, $payloadData) use (&$payload): array {
            $payload = $payloadData;

            return [];
        },
    );

    app(QueueFactory::class)
        ->connection('sync')
        ->push(new TracedJob);

    expect($payload)
        ->not->toBeNull()
        ->and($payload['laravel-trace'])
        ->toMatchArray([
            'trace_id' => $trace->id->value,
            'span_id' => $parent->span()->id->value,
        ]);

    $parent->close();
});

it('restores trace context from the queue payload', function (): void {
    $tracer = app(Tracer::class);

    $trace = $tracer->start('QueueTest');

    $parent = $tracer->span(
        name: 'dispatch.job',
        type: SpanType::Action,
    );

    Queue::createPayloadUsing(
        function ($connection, $queue, $payloadData) use ($tracer): array {
            return [
                'laravel-trace' => $tracer->context()?->toArray(),
            ];
        },
    );

    app(QueueFactory::class)
        ->connection('sync')
        ->push(new TracedJob);

    $tracer->clearContext();

    expect($tracer->context())->toBeNull();

    // The sync driver processes the job immediately, so the
    // JobProcessing event has already restored the context.
    $queueSpan = collect(app(InMemorySpanRecorder::class)->all())
        ->firstWhere('name', 'queue.job');

    expect($queueSpan)
        ->not->toBeNull()
        ->and($queueSpan->traceId->value)
        ->toBe($trace->id->value)
        ->and($queueSpan->parentId->value)
        ->toBe($parent->span()->id->value);

    $parent->close();
});

it('does not leak queue trace context after a job is processed', function (): void {
    $tracer = app(Tracer::class);

    $tracer->start('QueueTest');

    app(QueueFactory::class)
        ->connection('sync')
        ->push(new TracedJob);

    expect($tracer->context())
        ->toBeNull();
});

it('does not leak queue trace context after a job fails', function (): void {
    $tracer = app(Tracer::class);

    $tracer->start('QueueTest');

    expect(fn () => app(QueueFactory::class)
        ->connection('sync')
        ->push(new FailingJob))
        ->toThrow(RuntimeException::class, 'Job failed.')
        ->and($tracer->context())
        ->toBeNull();
});
