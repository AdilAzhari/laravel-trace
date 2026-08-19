<?php

declare(strict_types=1);

namespace LaravelTrace\LaravelTrace\Tracing;

use DateTimeImmutable;
use LaravelTrace\LaravelTrace\Context\TraceContext;
use LaravelTrace\LaravelTrace\Contracts\SpanRecorder;
use LaravelTrace\LaravelTrace\Contracts\TraceContextStore;
use LaravelTrace\LaravelTrace\Contracts\Tracer as TracerContract;
use LaravelTrace\LaravelTrace\Contracts\TraceRecorder;
use LaravelTrace\LaravelTrace\Span\Span;
use LaravelTrace\LaravelTrace\Span\SpanType;
use LaravelTrace\LaravelTrace\Trace\Trace;
use LogicException;
use Throwable;

final readonly class Tracer implements TracerContract
{
    public function __construct(
        private TraceContextStore $contextStore,
        private SpanRecorder $spanRecorder,
        private TraceRecorder $traceRecorder,
    ) {}

    public function start(string $name, array $attributes = []): Trace
    {
        $trace = Trace::start($name, $attributes);

        $this->setContext(
            new TraceContext(
                traceId: $trace->id,
            ),
        );

        return $trace;
    }

    public function span(
        string $name,
        SpanType $type,
        array $attributes = [],
    ): SpanScope {
        $previousContext = $this->context();

        if ($previousContext === null) {
            throw new LogicException(
                'Cannot start a span without an active trace.',
            );
        }

        $span = Span::start(
            traceId: $previousContext->traceId,
            name: $name,
            type: $type,
            parentId: $previousContext->spanId,
            attributes: $attributes,
        );

        $this->setContext(
            $previousContext->withSpan($span->id),
        );

        return new SpanScope(
            span: $span,
            previousContext: $previousContext,
            tracer: $this,
        );
    }

    public function context(): ?TraceContext
    {
        return $this->contextStore->get();
    }

    public function setContext(TraceContext $context): void
    {
        $this->contextStore->set($context);
    }

    public function clearContext(): void
    {
        $this->contextStore->clear();
    }

    public function record(Span $span): Span
    {
        $this->spanRecorder->record($span);

        return $span;
    }

    public function completeSpan(Span $span): Span
    {
        $completed = $span->completeSpan(
            new DateTimeImmutable,
        );

        $this->spanRecorder->record($completed);

        return $completed;
    }

    public function failSpan(
        Span $span,
        Throwable $exception,
    ): Span {
        $failed = $span->failSpan(
            exception: $exception,
            finishedAt: new DateTimeImmutable,
        );

        $this->spanRecorder->record($failed);

        return $failed;
    }

    public function completeTrace(Trace $trace): Trace
    {
        $completed = $trace->complete(
            new DateTimeImmutable,
        );

        $this->traceRecorder->record($completed);

        return $completed;
    }

    public function failTrace(
        Trace $trace,
        Throwable $exception,
    ): Trace {
        $failed = $trace->fail(
            exception: $exception,
            finishedAt: new DateTimeImmutable,
        );

        $this->traceRecorder->record($failed);

        return $failed;
    }
}
