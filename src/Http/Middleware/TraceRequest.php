<?php

declare(strict_types=1);

namespace LaravelTrace\LaravelTrace\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use LaravelTrace\LaravelTrace\Contracts\Tracer;
use LaravelTrace\LaravelTrace\Span\SpanType;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final readonly class TraceRequest
{
    public function __construct(
        private Tracer $tracer,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(
        Request $request,
        Closure $next,
    ): Response {
        $trace = $this->tracer->start(
            name: 'http.request',
            attributes: [
                'http.method' => $request->method(),
                'http.path' => $request->path(),
            ],
        );

        $scope = $this->tracer->context() !== null
            ? $this->tracer->span(
                name: 'http.request',
                type: SpanType::Http,
            )
            : null;

        try {
            $response = $next($request);

            $scope?->attributes([
                'http.status_code' => $response->getStatusCode(),
            ]);

            $scope?->close();

            $this->tracer->completeTrace($trace);

            return $response;
        } catch (Throwable $exception) {
            $scope?->fail($exception);

            $this->tracer->failTrace(
                trace: $trace,
                exception: $exception,
            );

            throw $exception;
        } finally {
            $this->tracer->clearContext();
        }
    }
}
