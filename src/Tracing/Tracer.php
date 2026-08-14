<?php

declare(strict_types=1);

namespace LaravelTrace\LaravelTrace\Tracing;

use DateTimeImmutable;
use LaravelTrace\LaravelTrace\Context\TraceContext;
use LaravelTrace\LaravelTrace\Contracts\TraceContextStore;
use LaravelTrace\LaravelTrace\Contracts\Tracer as TracerContract;
use LaravelTrace\LaravelTrace\Span\Span;
use LaravelTrace\LaravelTrace\Span\SpanType;
use LaravelTrace\LaravelTrace\Trace\Trace;
use LogicException;
use RuntimeException;
use Throwable;

final readonly class Tracer implements TracerContract
{
    public function __construct(
        private TraceContextStore $contextStore,
    ) {}

    public function start(string $name): Trace
    {
        $trace = Trace::start($name);

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

    public function complete(Span $span): Span
    {
        return $span->complete(
            new DateTimeImmutable,
        );
    }

    public function fail(Span $span,Throwable $exception,): Span
    {
        return $span->fail(
            $exception,
            new DateTimeImmutable,
        );
    }
}
