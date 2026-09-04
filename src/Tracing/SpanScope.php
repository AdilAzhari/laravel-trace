<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Tracing;

use AdilAzhari\LaravelTrace\Context\TraceContext;
use AdilAzhari\LaravelTrace\Contracts\SpanCompleter;
use AdilAzhari\LaravelTrace\Contracts\TraceContextStore;
use AdilAzhari\LaravelTrace\Span\Span;
use Throwable;

final class SpanScope
{
    private bool $closed = false;

    public function __construct(
        private Span $span,
        private readonly TraceContext $previousContext,
        private readonly SpanCompleter $spanCompleter,
        private readonly TraceContextStore $contextStore,
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

        $completed = $this->spanCompleter->completeSpan($this->span);

        $this->contextStore->set($this->previousContext);

        return $completed;
    }

    public function fail(Throwable $exception): Span
    {
        if ($this->closed) {
            return $this->span;
        }

        $this->closed = true;

        $failed = $this->spanCompleter->failSpan(
            $this->span,
            $exception,
        );

        $this->contextStore->set($this->previousContext);

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
