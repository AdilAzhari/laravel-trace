<?php

declare(strict_types=1);

namespace LaravelTrace\LaravelTrace\Tracing;

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
        $listener,
        $wildcard = false,
    ): Closure {
        $callable = parent::makeListener(
            $listener,
            $wildcard,
        );

        if ($wildcard || $this->isQueuedListener($listener)) {
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
        $class = match (true) {
            is_string($listener) => $this->parseClassCallable($listener)[0],
            is_array($listener) => $listener[0],
            default => null,
        };

        if ($class === null) {
            return false;
        }

        return $this->handlerShouldBeQueued($class);
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
