<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Contracts;

use AdilAzhari\LaravelTrace\Span\Span;

interface SpanRecorder
{
    public function record(Span $span): void;
}
