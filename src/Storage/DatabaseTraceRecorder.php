<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Storage;

use AdilAzhari\LaravelTrace\Contracts\TraceRecorder;
use AdilAzhari\LaravelTrace\Models\TraceRecord;
use AdilAzhari\LaravelTrace\Trace\Trace;
use AdilAzhari\LaravelTrace\Tracing\Tracer;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Persists traces to the database, keyed by trace ID so a trace recorded
 * `Running` at {@see Tracer::start()} and
 * again with its terminal status is one row, not two.
 *
 * Never lets a storage failure escape into the host application: every write
 * is caught, logged, and swallowed (unless
 * `laravel-trace.storage.database.swallow_exceptions` is disabled). After
 * the first failure this instance stops attempting further writes, since a
 * broken connection or missing table will not recover mid-request.
 */
final class DatabaseTraceRecorder implements TraceRecorder
{
    private bool $disabled = false;

    public function __construct(
        private readonly ConfigRepository $config,
        private readonly LoggerInterface $logger,
    ) {}

    public function record(Trace $trace): void
    {
        if ($this->disabled) {
            return;
        }

        try {
            RecordsWithoutTracing::run(
                fn () => TraceRecord::on($this->connection())
                    ->updateOrCreate(
                        ['id' => $trace->id->value],
                        $this->toAttributes($trace),
                    ),
            );
        } catch (Throwable $exception) {
            $this->disabled = true;

            if (! (bool) $this->config->get(
                'laravel-trace.storage.database.swallow_exceptions',
                true,
            )) {
                throw $exception;
            }

            $this->logger->warning(
                'laravel-trace: failed to persist a trace to the database.',
                ['exception' => $exception],
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function toAttributes(Trace $trace): array
    {
        return [
            'name' => $trace->name,
            'status' => $trace->status->value,
            'started_at' => $trace->startedAt,
            'finished_at' => $trace->finishedAt,
            'duration_ms' => $this->durationMs($trace),
            'error_type' => $trace->error?->type,
            'error_message' => $trace->error?->message,
            'error_file' => $trace->error?->file,
            'error_line' => $trace->error?->line,
            'attributes' => $trace->attributes,
        ];
    }

    private function durationMs(Trace $trace): ?float
    {
        if ($trace->finishedAt === null) {
            return null;
        }

        return ($trace->finishedAt->format('U.u') - $trace->startedAt->format('U.u')) * 1000;
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
