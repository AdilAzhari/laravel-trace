<?php

declare(strict_types=1);

namespace LaravelTrace\LaravelTrace\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use LaravelTrace\LaravelTrace\Contracts\Tracer;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final readonly class TraceRequest
{
    public function __construct(
        private Tracer $tracer,
    ) {}

    public function handle(
        Request $request,
        Closure $next,
    ): Response {
        $trace = $this->tracer->start(
            sprintf(
                'HTTP %s %s',
                $request->method(),
                $request->path(),
            ),
        );

        try {
            $response = $next($request);

            // Temporary: we'll add trace completion next.
            return $response;
        } catch (Throwable $exception) {
            throw $exception;
        } finally {
            $this->tracer->clearContext();
        }
    }
}
