<?php

namespace LaravelTrace\LaravelTrace\Contracts;

use LaravelTrace\LaravelTrace\Span\Span;

interface TraceRecorder
{
    public function record(Span $span): void;
}
