<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Tracing;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Events\Dispatcher;

final class TracingEventDispatcher extends Dispatcher
{
    public function __construct(
        private readonly EventListenerTracer $listenerTracer,
        ?Container $container = null,
    ) {
        parent::__construct($container);
    }

    /**
     * @param  Closure|string|array{class-string, string}  $listener
     */
    public function makeListener(
        mixed $listener,
        mixed $wildcard = false,
    ): Closure {
        $callable = parent::makeListener(
            $listener,
            $wildcard,
        );

        if (
            $wildcard
            || $this->isQueuedListener($listener)
            || $this->isInternalListener($listener)
        ) {
            return $callable;
        }

        return function (mixed $event, array $payload) use (
            $callable,
            $listener,
        ) {
            return $this->listenerTracer->trace(
                listener: fn () => $callable($event, $payload),
                name: $this->listenerName($listener),
            );
        };
    }

    /**
     * @param  Closure|string|array{class-string, string}  $listener
     */
    private function isQueuedListener(mixed $listener): bool
    {
        $class = $this->listenerClass($listener);

        if ($class === null) {
            return false;
        }

        return $this->handlerShouldBeQueued($class);
    }

    /**
     * The package's own queue/database instrumentation listeners manage the
     * trace context themselves. Wrapping them in a listener span would make
     * the wrapper re-apply the context snapshot it captured before the
     * listener ran, undoing the context the listener deliberately cleared
     * and leaking queue execution context after every job.
     *
     * @param  Closure|string|array{class-string, string}  $listener
     */
    private function isInternalListener(mixed $listener): bool
    {
        $class = $this->listenerClass($listener);

        return $class !== null
            && str_starts_with($class, __NAMESPACE__.'\\');
    }

    /**
     * @param  Closure|string|array{class-string, string}  $listener
     * @return class-string|null
     */
    private function listenerClass(mixed $listener): ?string
    {
        return match (true) {
            is_string($listener) => $this->parseClassCallable($listener)[0],
            is_array($listener) => $listener[0],
            default => null,
        };
    }

    private function listenerName(mixed $listener): string
    {
        if (is_string($listener)) {
            return 'listener.'.$listener;
        }

        if (
            is_array($listener)
            && isset($listener[0])
            && is_string($listener[0])
        ) {
            return 'listener.'.$listener[0];
        }

        return 'listener.closure';
    }
}
