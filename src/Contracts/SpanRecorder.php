<?php

declare(strict_types=1);

namespace LaravelTrace\LaravelTrace\Contracts;

use LaravelTrace\LaravelTrace\Span\Span;

interface SpanRecorder
{
    public function record(Span $span): void;
}
