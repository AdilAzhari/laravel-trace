<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Contracts;

use AdilAzhari\LaravelTrace\Span\Span;
use Throwable;

interface SpanCompleter
{
    public function completeSpan(Span $span): Span;

    public function failSpan(
        Span $span,
        Throwable $exception,
    ): Span;
}
