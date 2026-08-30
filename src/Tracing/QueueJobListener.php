<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Tracing;

use AdilAzhari\LaravelTrace\Context\TraceContext;
use AdilAzhari\LaravelTrace\Contracts\Tracer;
use AdilAzhari\LaravelTrace\Span\SpanType;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;

final class QueueJobListener
{
    /**
     * @var array<int, SpanScope>
     */
    private array $scopes = [];

    /**
     * @var array<int, bool>
     */
    private array $injectedContexts = [];

    public function __construct(
        private readonly Tracer $tracer,
        private readonly ConfigRepository $config,
    ) {}

    public function handleProcessed(JobProcessed $event): void
    {
        $key = $this->jobKey($event->job);

        $scope = $this->scopes[$key] ?? null;

        unset($this->scopes[$key]);

        $scope?->close();

        $this->clearInjectedContext($key);
    }

    public function handleException(JobExceptionOccurred $event): void
    {
        $key = $this->jobKey($event->job);

        $scope = $this->scopes[$key] ?? null;

        unset($this->scopes[$key]);

        $scope?->fail($event->exception);

        $this->clearInjectedContext($key);
    }

    private function clearInjectedContext(int $key): void
    {
        if (($this->injectedContexts[$key] ?? false) !== true) {
            return;
        }

        $this->tracer->clearContext();

        unset($this->injectedContexts[$key]);
    }

    public function handleProcessing(JobProcessing $event): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $key = $this->jobKey($event->job);

        $context = $event->job->payload()['laravel-trace'] ?? null;

        if ($context !== null) {
            $this->tracer->setContext(
                TraceContext::fromArray($context),
            );

            $this->injectedContexts[$key] = true;
        }

        if ($this->tracer->context() === null) {
            return;
        }

        $this->scopes[$key] = $this->tracer->span(
            name: 'queue.job',
            type: SpanType::Job,
            attributes: [
                'queue.connection' => $event->connectionName,
                'queue.name' => $event->job->getQueue(),
                'queue.job' => $event->job->resolveName(),
            ],
        );
    }

    private function isEnabled(): bool
    {
        return (bool) $this->config->get(
            'laravel-trace.queue.enabled',
            true,
        );
    }

    private function jobKey(object $job): int
    {
        return spl_object_id($job);
    }
}
