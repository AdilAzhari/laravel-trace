<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Tracing;

use AdilAzhari\LaravelTrace\Context\TraceContext;
use AdilAzhari\LaravelTrace\Contracts\SpanRecorder;
use AdilAzhari\LaravelTrace\Contracts\TraceContextStore;
use AdilAzhari\LaravelTrace\Contracts\Tracer as TracerContract;
use AdilAzhari\LaravelTrace\Contracts\TraceRecorder;
use AdilAzhari\LaravelTrace\Span\Span;
use AdilAzhari\LaravelTrace\Span\SpanType;
use AdilAzhari\LaravelTrace\Trace\Trace;
use DateTimeImmutable;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use LogicException;
use Throwable;

final readonly class Tracer implements TracerContract
{
    public function __construct(
        private TraceContextStore $contextStore,
        private SpanRecorder $spanRecorder,
        private TraceRecorder $traceRecorder,
        private ?ConfigRepository $config = null,
    ) {}

    /**
     * @param  array<string, string|int|float|bool|null>  $attributes
     */
    public function start(string $name, array $attributes = []): Trace
    {
        $trace = Trace::start($name, $attributes);

        if ($this->isEnabled()) {
            $this->setContext(
                new TraceContext(
                    traceId: $trace->id,
                ),
            );
        }

        return $trace;
    }

    /**
     * Read live rather than capturing a boolean at construction time: this
     * class is resolved early via the container's 'events' -> event
     * dispatcher -> listener tracer dependency chain, before test/runtime
     * config overrides to 'laravel-trace.enabled' would have taken effect.
     */
    private function isEnabled(): bool
    {
        return $this->config === null
            || (bool) $this->config->get('laravel-trace.enabled', true);
    }

    /**
     * @param  array<string, string|int|float|bool|null>  $attributes
     */
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
