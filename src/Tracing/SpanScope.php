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
     * Merges the given attributes into the span, keeping whatever was set
     * before - it never replaces the existing set, so calling this more
     * than once accumulates rather than overwriting. Exact alias of
     * {@see self::addAttributes()}; the two exist as two natural spellings
     * of the same operation, kept here so both read naturally at the call
     * site depending on whether the span is being given its attributes for
     * the first time or having more added to it later.
     *
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
     * Merges the given attributes into the span, keeping whatever was set
     * before - it never replaces the existing set. Exact alias of
     * {@see self::attributes()}; see that method's docblock for why both
     * exist.
     *
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
