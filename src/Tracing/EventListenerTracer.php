<?php

declare(strict_types=1);

namespace LaravelTrace\LaravelTrace\Tracing;

use LaravelTrace\LaravelTrace\Contracts\Tracer;
use LaravelTrace\LaravelTrace\Span\SpanType;
use Throwable;

final readonly class EventListenerTracer
{
    public function __construct(
        private Tracer $tracer,
    ) {}

    /**
     * @param  callable(): mixed  $listener
     *
     * @throws Throwable
     */
    public function trace(
        callable $listener,
        string $name,
    ): mixed {
        if ($this->tracer->context() === null) {
            return $listener();
        }

        $scope = $this->tracer->span(
            name: $name,
            type: SpanType::Listener,
        );

        try {
            $result = $listener();

            $scope->close();

            return $result;
        } catch (Throwable $exception) {
            $scope->fail($exception);

            throw $exception;
        }
    }
}
