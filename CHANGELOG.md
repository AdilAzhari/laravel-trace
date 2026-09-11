# Release Notes

## [Unreleased](https://github.com/adilazhari/laravel-trace/compare/v0.1.0...1.x)

### Added

- Database storage driver: set `laravel-trace.storage.driver` to `database` to
  persist traces and spans to `laravel_traces` / `laravel_trace_spans` via
  `DatabaseTraceRecorder` / `DatabaseSpanRecorder`, instead of the in-memory
  recorders. Configure the connection with `laravel-trace.storage.database.connection`.
- A storage failure (bad connection, missing table) is logged and swallowed
  by default so tracing never breaks the host application; set
  `laravel-trace.storage.database.swallow_exceptions` to `false` to let it
  surface while debugging your setup.
- Read API for persisted traces and spans: `TraceReader` / `SpanReader`
  contracts, immutable `TraceQuery` / `SpanQuery` descriptions, and the
  `LaravelTrace` facade (`trace()`, `traces()`, `span()`, `spans()`,
  `spansForTrace()`, `spanTree()`). Filter by id, name, status, started-at
  range, duration range, error presence, and attribute equality; sort by a
  whitelisted column with a deterministic `id` tiebreak; offset pagination.
  Which reader the container resolves follows `laravel-trace.storage.driver`,
  and the `memory` and `database` drivers return identical results for the
  same query. `SpanTree` builds the parent/child tree from a flat span list.
- `laravel_traces` gains a `[name, started_at]` index and
  `laravel_trace_spans` a `[type, started_at]` index to support the new
  filters.

### Changed

- `Tracer::start()` now records the trace immediately as `Running`, in
  addition to recording its terminal state on completion/failure, so a
  database storage driver enforcing a foreign key from spans to their trace
  has a parent row to reference before any span is recorded. Recorder
  implementations (including `InMemoryTraceRecorder`) must be idempotent by
  trace/span ID as a result.
- `InMemorySpanRecorder` is now idempotent by span ID (it previously
  appended), matching its contract and the `InMemoryTraceRecorder`.
- The in-memory recorders now write through shared `InMemoryTraceStore` /
  `InMemorySpanStore` singletons, so the in-memory readers see exactly what
  was recorded.
- `TraceRecord` / `SpanRecord` use a microsecond datetime format so the
  microsecond precision the migration declares survives a round trip through
  text-based drivers (SQLite); a database-hydrated trace/span now matches its
  in-memory twin.
- The trace/span column mapping moved into `TraceRecordMapper` /
  `SpanRecordMapper`, shared by the database recorder and reader. Added
  `Trace::durationMs()` to mirror `Span::durationMs()`.
- Renamed the migration `..._create_laravel_trace_placeholder_table.php` to
  `..._create_laravel_trace_tables.php` and added `duration_ms`,
  microsecond-precision `started_at`/`finished_at`, and a composite
  `[trace_id, started_at]` index.


## [v0.1.0](https://github.com/adilazhari/laravel-trace/compare/...v0.1.0) - 202x-xx-xx

Initial pre-release.
