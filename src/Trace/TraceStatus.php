<?php

declare(strict_types=1);

namespace LaravelTrace\LaravelTrace\Trace;

enum TraceStatus: string
{
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
}
