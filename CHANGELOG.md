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

### Changed

- `Tracer::start()` now records the trace immediately as `Running`, in
  addition to recording its terminal state on completion/failure, so a
  database storage driver enforcing a foreign key from spans to their trace
  has a parent row to reference before any span is recorded. Recorder
  implementations (including `InMemoryTraceRecorder`) must be idempotent by
  trace/span ID as a result.
- Renamed the migration `..._create_laravel_trace_placeholder_table.php` to
  `..._create_laravel_trace_tables.php` and added `duration_ms`,
  microsecond-precision `started_at`/`finished_at`, and a composite
  `[trace_id, started_at]` index.


## [v0.1.0](https://github.com/adilazhari/laravel-trace/compare/...v0.1.0) - 202x-xx-xx

Initial pre-release.
