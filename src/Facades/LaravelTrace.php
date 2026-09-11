<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \AdilAzhari\LaravelTrace\Trace\Trace|null trace(\AdilAzhari\LaravelTrace\Trace\TraceId|string $id)
 * @method static \AdilAzhari\LaravelTrace\Read\TraceQueryBuilder traces()
 * @method static \AdilAzhari\LaravelTrace\Span\Span|null span(\AdilAzhari\LaravelTrace\Span\SpanId|string $id)
 * @method static \AdilAzhari\LaravelTrace\Read\SpanQueryBuilder spans()
 * @method static \Illuminate\Support\Collection<int, \AdilAzhari\LaravelTrace\Span\Span> spansForTrace(\AdilAzhari\LaravelTrace\Trace\TraceId|string $traceId)
 * @method static list<\AdilAzhari\LaravelTrace\Read\SpanNode> spanTree(\AdilAzhari\LaravelTrace\Trace\TraceId|string $traceId)
 *
 * @see \AdilAzhari\LaravelTrace\LaravelTrace
 */
class LaravelTrace extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \AdilAzhari\LaravelTrace\LaravelTrace::class;
    }
}
