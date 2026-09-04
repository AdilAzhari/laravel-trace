<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Http\Middleware;

use AdilAzhari\LaravelTrace\Context\TraceContext;
use AdilAzhari\LaravelTrace\Contracts\Tracer;
use AdilAzhari\LaravelTrace\Span\SpanType;
use Closure;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final readonly class TraceRequest
{
    public const string DEFAULT_HEADER = 'X-Trace-Context';

    public function __construct(
        private Tracer $tracer,
        private ?ConfigRepository $config = null,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(
        Request $request,
        Closure $next,
    ): Response {
        $attributes = [
            'http.method' => $request->method(),
            'http.path' => $request->path(),
        ];

        $inbound = $this->inboundContext($request);

        // With an inbound context this request continues a distributed trace
        // that an upstream service owns, so no local root trace is started or
        // recorded - the same way a queued job restored from its payload
        // behaves. Without one, behavior is unchanged: a fresh root trace is
        // started and completed here.
        $trace = $inbound === null
            ? $this->tracer->start('http.request', $attributes)
            : null;

        if ($inbound !== null) {
            $this->tracer->setContext($inbound);
        }

        $scope = $this->tracer->context() !== null
            ? $this->tracer->span(
                name: 'http.request',
                type: SpanType::Http,
                attributes: $inbound !== null ? $attributes : [],
            )
            : null;

        try {
            $response = $next($request);

            $scope?->attributes([
                'http.status_code' => $response->getStatusCode(),
            ]);

            $scope?->close();

            if ($trace !== null) {
                $this->tracer->completeTrace($trace);
            }

            return $response;
        } catch (Throwable $exception) {
            $scope?->fail($exception);

            if ($trace !== null) {
                $this->tracer->failTrace(
                    trace: $trace,
                    exception: $exception,
                );
            }

            throw $exception;
        } finally {
            $this->tracer->clearContext();
        }
    }

    private function inboundContext(Request $request): ?TraceContext
    {
        if (! $this->enabled()) {
            return null;
        }

        $header = $request->header($this->headerName());

        if (! is_string($header) || trim($header) === '') {
            return null;
        }

        return TraceContext::fromHeader($header);
    }

    private function enabled(): bool
    {
        return $this->config === null
            || (bool) $this->config->get('laravel-trace.enabled', true);
    }

    private function headerName(): string
    {
        $name = $this->config?->get(
            'laravel-trace.http.header',
            self::DEFAULT_HEADER,
        );

        return is_string($name) && $name !== ''
            ? $name
            : self::DEFAULT_HEADER;
    }
}
