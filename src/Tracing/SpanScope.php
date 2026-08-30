<?php

declare(strict_types=1);

namespace LaravelTrace\LaravelTrace\Tracing;

use LaravelTrace\LaravelTrace\Context\TraceContext;
use LaravelTrace\LaravelTrace\Span\Span;
use Throwable;

final class SpanScope
{
    private bool $closed = false;

    public function __construct(
        private Span $span,
        private readonly TraceContext $previousContext,
        private readonly Tracer $tracer,
    ) {}

    public function span(): Span
    {
        return $this->span;
    }

    public function close(): Span
    {
        if ($this->closed) {
            return $this->span;
        }

        $this->closed = true;

        $completed = $this->tracer->completeSpan(
            $this->span,
        );

        $this->tracer->setContext(
            $this->previousContext,
        );

        return $completed;
    }

    public function fail(Throwable $exception): Span
    {
        if ($this->closed) {
            return $this->span;
        }

        $this->closed = true;

        $failed = $this->tracer->failSpan(
            $this->span,
            $exception,
        );

        $this->tracer->setContext(
            $this->previousContext,
        );

        return $failed;
    }

    /**
     * @param  array<string, string|int|float|bool|null>  $attributes
     */
    public function attributes(array $attributes): self
    {
        if ($this->closed) {
            return $this;
        }

        $this->span = $this->span->withAttributes($attributes);

        return $this;
    }

    /**
     * @param  array<string, string|int|float|bool|null>  $attributes
     */
    public function addAttributes(array $attributes): self
    {
        if ($this->closed) {
            return $this;
        }

        $this->span = $this->span->withAttributes($attributes);

        return $this;
    }
}
