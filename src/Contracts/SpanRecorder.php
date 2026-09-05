<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Contracts;

use AdilAzhari\LaravelTrace\Span\Span;

interface SpanRecorder
{
    /**
     * Persist a span. Implementations MUST be idempotent by
     * {@see Span::$id}: recording the same span ID more than once replaces
     * the earlier record rather than duplicating it.
     */
    public function record(Span $span): void;
}
