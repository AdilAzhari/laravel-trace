<?php

declare(strict_types=1);

namespace LaravelTrace\LaravelTrace\Contracts;

use LaravelTrace\LaravelTrace\Trace\Trace;

interface TraceRecorder
{
    public function record(Trace $trace): void;
}
