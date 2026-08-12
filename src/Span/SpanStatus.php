<?php

declare(strict_types=1);

namespace LaravelTrace\LaravelTrace\Span;

enum SpanStatus: string
{
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
}
