<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Tests\Fixtures\Jobs;

use AdilAzhari\LaravelTrace\Context\TraceContext;
use AdilAzhari\LaravelTrace\Contracts\Tracer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Dispatches a TracedJob synchronously from inside its own handle(),
 * recording the context it sees on either side of that nested dispatch.
 */
final class DispatchesSyncJob implements ShouldQueue
{
    use Queueable;

    public static ?TraceContext $contextBeforeInner = null;

    public static ?TraceContext $contextAfterInner = null;

    public function handle(Tracer $tracer): void
    {
        self::$contextBeforeInner = $tracer->context();

        dispatch_sync(new TracedJob);

        self::$contextAfterInner = $tracer->context();
    }
}
