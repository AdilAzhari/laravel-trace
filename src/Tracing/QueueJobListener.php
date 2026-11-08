<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Tracing;

use AdilAzhari\LaravelTrace\Config\ConfigBoolean;
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
     * The context that was active before a job's payload context was
     * injected, keyed by job. Restored once the job finishes, so a sync
     * job hands the caller its context back and a worker job leaves none
     * behind. The value may legitimately be null.
     *
     * @var array<int, TraceContext|null>
     */
    private array $previousContexts = [];

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

        $this->restorePreviousContext($key);
    }

    public function handleException(JobExceptionOccurred $event): void
    {
        $key = $this->jobKey($event->job);

        $scope = $this->scopes[$key] ?? null;

        unset($this->scopes[$key]);

        $scope?->fail($event->exception);

        $this->restorePreviousContext($key);
    }

    private function restorePreviousContext(int $key): void
    {
        if (! array_key_exists($key, $this->previousContexts)) {
            return;
        }

        $previous = $this->previousContexts[$key];

        unset($this->previousContexts[$key]);

        if ($previous !== null) {
            $this->tracer->setContext($previous);

            return;
        }

        $this->tracer->clearContext();
    }

    public function handleProcessing(JobProcessing $event): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $key = $this->jobKey($event->job);

        $context = $event->job->payload()['laravel-trace'] ?? null;

        if ($context !== null) {
            $this->previousContexts[$key] = $this->tracer->context();

            $this->tracer->setContext(
                TraceContext::fromArray($context),
            );
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
        return ConfigBoolean::resolve($this->config->get(
            'laravel-trace.queue.enabled',
            true,
        ), true);
    }

    private function jobKey(object $job): int
    {
        return spl_object_id($job);
    }
}
