<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Storage;

use AdilAzhari\LaravelTrace\Contracts\SpanRecorder;
use AdilAzhari\LaravelTrace\Models\SpanRecord;
use AdilAzhari\LaravelTrace\Models\TraceRecord;
use AdilAzhari\LaravelTrace\Span\Span;
use AdilAzhari\LaravelTrace\Trace\Trace;
use AdilAzhari\LaravelTrace\Trace\TraceStatus;
use AdilAzhari\LaravelTrace\Tracing\Tracer;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Persists spans to the database, keyed by span ID so a duplicate record for
 * the same span replaces rather than duplicates it.
 *
 * `laravel_trace_spans.trace_id` has a foreign key to `laravel_traces.id`.
 * A span belonging to a trace that owns no local {@see Trace}
 * object — a request or queue job continuing a trace propagated from
 * upstream — would otherwise violate that constraint, since nothing ever
 * calls {@see Tracer::start()} for it. This
 * recorder ensures a placeholder trace row exists before inserting the span
 * in that case; it stays `running` since only the owning service ever
 * completes it.
 *
 * Never lets a storage failure escape into the host application: see
 * {@see DatabaseTraceRecorder} for the same failure-isolation behaviour.
 */
final class DatabaseSpanRecorder implements SpanRecorder
{
    private bool $disabled = false;

    public function __construct(
        private readonly ConfigRepository $config,
        private readonly LoggerInterface $logger,
    ) {}

    public function record(Span $span): void
    {
        if ($this->disabled) {
            return;
        }

        try {
            RecordsWithoutTracing::run(function () use ($span): void {
                $this->ensureTraceExists($span);

                SpanRecord::on($this->connection())
                    ->updateOrCreate(
                        ['id' => $span->id->value],
                        $this->toAttributes($span),
                    );
            });
        } catch (Throwable $exception) {
            $this->disabled = true;

            if (! (bool) $this->config->get(
                'laravel-trace.storage.database.swallow_exceptions',
                true,
            )) {
                throw $exception;
            }

            $this->logger->warning(
                'laravel-trace: failed to persist a span to the database.',
                ['exception' => $exception],
            );
        }
    }

    private function ensureTraceExists(Span $span): void
    {
        TraceRecord::on($this->connection())->firstOrCreate(
            ['id' => $span->traceId->value],
            [
                'name' => 'unknown',
                'status' => TraceStatus::Running->value,
                'started_at' => $span->startedAt,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toAttributes(Span $span): array
    {
        return [
            'trace_id' => $span->traceId->value,
            'parent_id' => $span->parentId?->value,
            'name' => $span->name,
            'type' => $span->type->value,
            'status' => $span->status->value,
            'started_at' => $span->startedAt,
            'finished_at' => $span->finishedAt,
            'duration_ms' => $span->durationMs(),
            'error_type' => $span->error?->type,
            'error_message' => $span->error?->message,
            'error_file' => $span->error?->file,
            'error_line' => $span->error?->line,
            'attributes' => $span->attributes,
        ];
    }

    private function connection(): ?string
    {
        /** @var string|null $connection */
        $connection = $this->config->get(
            'laravel-trace.storage.database.connection',
        );

        return $connection;
    }
}
