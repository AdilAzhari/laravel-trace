<div align="center">
    <h1>Laravel Trace</h1>
</div>

<p align="center">
    <a href="https://packagist.org/packages/adilazhari/laravel-trace"><img src="https://img.shields.io/packagist/v/adilazhari/laravel-trace.svg?style=flat-square" alt="Packagist"></a>
    <a href="https://packagist.org/packages/adilazhari/laravel-trace"><img src="https://img.shields.io/packagist/php-v/adilazhari/laravel-trace.svg?style=flat-square" alt="PHP from Packagist"></a>
    <a href="https://packagist.org/packages/adilazhari/laravel-trace"><img src="https://badge.laravel.cloud/badge/adilazhari/laravel-trace?style=flat" alt="Laravel versions"></a>
    <a href="https://github.com/adilazhari/laravel-trace/actions"><img alt="GitHub Workflow Status (main)" src="https://img.shields.io/github/actions/workflow/status/adilazhari/laravel-trace/tests.yml?branch=main&label=Tests&style=flat-square"></a>
    <a href="https://packagist.org/packages/adilazhari/laravel-trace"><img src="https://img.shields.io/packagist/dt/adilazhari/laravel-trace.svg?style=flat-square" alt="Total Downloads"></a>
</p>



## Installation

You can install the package via Composer:

```bash
composer require adilazhari/laravel-trace
```

You may publish all of the package's resources at once:

```bash
php artisan vendor:publish --tag="laravel-trace"
```

Or, you may publish each resource individually:

### Publishing the Configuration File

```bash
php artisan vendor:publish --tag="laravel-trace-config"
```

### Publishing and Running the Migrations

```bash
php artisan vendor:publish --tag="laravel-trace-migrations"
php artisan migrate
```

## Configuration

Every setting in `config/laravel-trace.php` can be overridden from `.env`
without publishing the config file. Defaults shown below are what applies
when the variable is unset.

| Config key | Env variable | Type | Default | Purpose |
|---|---|---|---|---|
| `enabled` | `LARAVEL_TRACE_ENABLED` | bool | `true` | Master switch for the whole package. |
| `instrumentation.database.enabled` | `LARAVEL_TRACE_DATABASE_QUERY_ENABLED` | bool | `true` | Records a span per SQL query. Not related to `storage.database.*` below - see "Database query instrumentation vs. database storage". |
| `queue.enabled` | `LARAVEL_TRACE_QUEUE_ENABLED` | bool | `true` | Restores/records trace context around queued job processing. |
| `http.propagate_outbound` | `LARAVEL_TRACE_HTTP_PROPAGATE_OUTBOUND` | bool | `false` | Attaches the active trace context to outbound `Http::` client requests. |
| `http.header` | *(not env-driven)* | string | `X-Trace-Context` | Header name used for inbound/outbound context propagation. |
| `storage.driver` | `LARAVEL_TRACE_STORAGE_DRIVER` | string | `memory` | Where traces/spans are persisted: `memory` or `database`. |
| `storage.database.connection` | *(not env-driven)* | string\|null | `null` | Connection used by the `database` storage driver; `null` uses the app's default connection. |
| `storage.database.swallow_exceptions` | `LARAVEL_TRACE_STORAGE_DATABASE_SWALLOW_EXCEPTIONS` | bool | `true` | Logs and swallows a database storage write failure instead of letting it throw. |
| `storage.retention.enabled` | `LARAVEL_TRACE_RETENTION_ENABLED` | bool | `false` | Whether `laravel-trace:prune` deletes anything without an explicit `--days`/`--before`. |
| `storage.retention.days` | `LARAVEL_TRACE_RETENTION_DAYS` | int | `7` | Age (in days) at which a terminal trace becomes eligible for pruning. |
| `storage.retention.chunk_size` | `LARAVEL_TRACE_RETENTION_CHUNK_SIZE` | int | `500` | Traces deleted per batch by the pruner. |

```dotenv
# .env
LARAVEL_TRACE_ENABLED=true
LARAVEL_TRACE_DATABASE_QUERY_ENABLED=true
LARAVEL_TRACE_QUEUE_ENABLED=true
LARAVEL_TRACE_HTTP_PROPAGATE_OUTBOUND=false
LARAVEL_TRACE_STORAGE_DRIVER=database
LARAVEL_TRACE_STORAGE_DATABASE_SWALLOW_EXCEPTIONS=true
LARAVEL_TRACE_RETENTION_ENABLED=true
LARAVEL_TRACE_RETENTION_DAYS=7
LARAVEL_TRACE_RETENTION_CHUNK_SIZE=500
```

Boolean env values accept `true`/`false`, `1`/`0`, or Laravel's `(true)`/`(false)`
forms; an unset or empty value falls back to the default rather than being
read as `false`.

### Database query instrumentation vs. database storage

These are two independent concerns that happen to share the word
"database":

- **`instrumentation.database.enabled`** - whether the package records a
  span for every SQL query your application runs. This has nothing to do
  with where traces/spans themselves are stored.
- **`storage.database.*`** - configuration for the optional `database`
  *storage driver* (connection, exception-swallowing) that traces/spans can
  be persisted to instead of the default `memory` driver. See
  [Database storage](#database-storage) below.

You can, for example, run with SQL query instrumentation off
(`LARAVEL_TRACE_DATABASE_QUERY_ENABLED=false`, so no `database.query` spans
are recorded) while still persisting whatever traces/spans *are* recorded to
the `database` storage driver, or vice versa - the two toggles are unrelated.

### Breaking change: `database.enabled` renamed

Pre-1.0, `laravel-trace.database.enabled` has been renamed to
`laravel-trace.instrumentation.database.enabled` to remove the ambiguity
with `storage.database.*` above. There is no compatibility alias for the old
key - keeping one silently would reintroduce the exact ambiguity this rename
fixes. If you have published `config/laravel-trace.php` or set
`LARAVEL_TRACE_DATABASE_ENABLED` anywhere, update it to
`instrumentation.database.enabled` / `LARAVEL_TRACE_DATABASE_QUERY_ENABLED`.

## Usage

### Registering the middleware

Laravel Trace never attaches itself to a route automatically. To trace HTTP
requests, register `TraceRequest` wherever you want tracing to start - a
route, a group, or every request.

```php
// routes/web.php
use AdilAzhari\LaravelTrace\Http\Middleware\TraceRequest;

Route::middleware(TraceRequest::class)->group(function () {
    Route::get('/orders/{order}', [OrderController::class, 'show']);
});
```

To trace every request instead, append it in `bootstrap/app.php`:

```php
use AdilAzhari\LaravelTrace\Http\Middleware\TraceRequest;
use Illuminate\Foundation\Configuration\Middleware;

->withMiddleware(function (Middleware $middleware) {
    $middleware->append(TraceRequest::class);
})
```

Without this middleware - or a manual `Tracer::start()` call, below - nothing
is traced: database queries, events, and queue jobs are only instrumented
while a trace is already active.

### Basic HTTP tracing

Once the middleware is applied to a route, every matching request is wrapped
in a trace and an `http.request` span with no further code:

```php
Route::middleware(TraceRequest::class)->get('/orders/{order}', function (Order $order) {
    return $order->fresh('items');
});
```

- The trace is named `http.request`, with `http.method` and `http.path`
  attributes captured up front.
- The span records `http.status_code` on success; if the route throws, the
  span and trace are both failed with the exception recorded, and the
  exception is rethrown unchanged.
- The trace context is always cleared once the response is produced -
  successfully or not - so it never leaks into whatever handles the next
  request in the same process.

### Manual instrumentation

For anything outside a traced HTTP request - a console command, a scheduled
task, or extra detail inside one - instrument code directly with the
`Tracer` contract (there's no facade for the write side; resolve it from the
container or inject `AdilAzhari\LaravelTrace\Contracts\Tracer`):

```php
use AdilAzhari\LaravelTrace\Contracts\Tracer;
use AdilAzhari\LaravelTrace\Span\SpanType;

$tracer = app(Tracer::class);

$trace = $tracer->start('import.customers', ['source' => 'csv']);

$span = $tracer->span('parse.file', SpanType::Action);

try {
    // ... do the work ...
    $span->close();
} catch (Throwable $exception) {
    $span->fail($exception);
    throw $exception;
}

$tracer->completeTrace($trace);
```

- `span()` throws `LogicException` if no trace is active yet - call
  `start()` first, or only instrument code that runs inside a request/job/
  listener that already has one.
- `$span->attributes([...])` (alias: `addAttributes()`) adds to a span
  before it closes; attributes are `string|int|float|bool|null` values only.

### Nested spans

Calling `span()` while another span is open nests it under whichever span is
currently active, and closing a span restores its parent as the active one -
parent/child relationships are never managed by hand:

```php
$outer = $tracer->span('process.order', SpanType::Action);
$inner = $tracer->span('charge.payment', SpanType::Action);

$inner->close(); // process.order becomes the active span again
$outer->close();
```

### Automatic instrumentation

Once a trace is active - via the middleware or a manual `start()` call - the
following are recorded with no further code:

| What | Span name | Type | Toggle |
|---|---|---|---|
| A database query | `database.query` | `SpanType::Database` | `laravel-trace.instrumentation.database.enabled` |
| A non-queued event listener | `listener.<class>` | `SpanType::Listener` | on whenever a trace is active |
| A queued job being processed | `queue.job` | `SpanType::Job` | `laravel-trace.queue.enabled` |

Wildcard listeners and the package's own internal listeners are never
wrapped, to avoid double-instrumenting and self-referential spans. A
database *storage* write failure (as opposed to an application query) is
caught, logged, and swallowed by default - see Database storage, below -
instrumentation itself never throws just because a query ran.

### Span types

`AdilAzhari\LaravelTrace\Span\SpanType`:

- `Http` - an HTTP request, recorded by the `TraceRequest` middleware.
- `Database` - a SQL query, recorded by the automatic instrumentation.
- `Listener` - a non-queued event listener, recorded automatically.
- `Job` - a queued job being processed, recorded automatically.
- `Action` - your own business-logic spans (manual instrumentation).
- `Event` - your own domain-event-shaped spans (manual instrumentation) -
  distinct from `Listener`, which is reserved for the framework's own event
  dispatch.

### Context propagation

A trace's identity crosses process boundaries as a `TraceContext`: a trace
ID and, if a span is active, the current span ID.

**HTTP.** An inbound request carrying the `X-Trace-Context` header (format
`<trace-id>` or `<trace-id>-<span-id>`, header name configurable via
`laravel-trace.http.header`) continues the propagated trace rather than
creating a new local root trace: `Tracer::start()` is never called for that
request, but spans are still recorded against the propagated trace ID as
normal. (The database storage driver additionally writes a placeholder
`Running` trace row so those spans have a parent to reference - see
Database storage, below - without ever creating a local `Trace` object.) A
malformed header is ignored and a fresh local trace starts instead.
Attaching the header to *outbound* requests made through Laravel's HTTP
client is opt-in:

```php
// config/laravel-trace.php
'http' => [
    'propagate_outbound' => true,
],
```

**Queues.** Dispatching a job while a trace is active automatically embeds
the current context in the job's payload. When the job is processed, that
context is restored before the job runs (and cleared again afterwards)
whenever `laravel-trace.queue.enabled` is true - no extra code needed on
either side of the dispatch.

### Failure behavior

- `Tracer::span()` throws `LogicException` when called with no active trace.
- A write to the `database` storage driver failing (bad connection, missing
  table) is logged and swallowed by default - see `storage.database.swallow_exceptions`
  below - so tracing failures never break the request or job being traced.
- Reading (`TraceReader`/`SpanReader`) and pruning (`TracePruner`) failures
  are **not** swallowed; they propagate, since those are calls your own code
  makes deliberately rather than ambient instrumentation.
- If a process is killed or fatals mid-trace, whatever trace/spans were
  already recorded stay `Running` permanently - there is no automatic
  reconciliation for this yet, and pruning (below) never deletes a `Running`
  row regardless of age.

### Memory driver limitations

The default `memory` driver is suitable mainly for short-lived debugging or
tests, not for relying on in a real deployment. Two different things are
involved here, and they don't share the same lifetime:

- **The active trace context** - which trace/span is currently open - is
  always cleared at the end of a request or job (by the `TraceRequest`
  middleware's `finally` block, or by `QueueJobListener` once a job
  finishes), regardless of how the underlying PHP process is managed.
- **The underlying `InMemoryTraceStore`/`InMemorySpanStore`**, however, are
  container singletons that only clear when the *application instance* they
  belong to is torn down - not necessarily when the OS process is. Under a
  classic (non-Octane) PHP-FPM or CLI request, Laravel rebuilds its
  container from scratch on every request, so in practice this store never
  outlives the request that created it, even though the underlying worker
  process itself is commonly reused for the next one. A runtime that
  deliberately keeps one application instance alive across many units of
  work instead - a `queue:work` worker processing job after job, or an
  Octane/Swoole worker serving request after request - keeps that same
  store alive too, and it accumulates every trace and span recorded for as
  long as the process runs, with nothing built in to bound it.
- Nothing persists between separate `artisan`/request processes in any case
  - see Querying and Pruning traces, below, for what that means for those
  features specifically.

Use the `database` driver (with retention pruning configured) rather than
`memory` for anything beyond short-lived local debugging in a long-lived
worker process.

If you must keep using the `memory` driver inside a long-lived worker,
`InMemoryStorageCleaner` gives you an explicit way to bound that growth
yourself, at whatever boundary you choose:

```php
use AdilAzhari\LaravelTrace\Tracing\InMemoryStorageCleaner;

app(InMemoryStorageCleaner::class)->clear();
```

This empties both `InMemoryTraceStore` and `InMemorySpanStore` together, so
you never clear one and leave the other stale. A few things to note:

- **It is entirely caller-controlled.** The package never calls this
  automatically at request or job termination - doing so would silently
  break reading back what the current process just recorded, which the
  `memory` driver otherwise supports for the life of the application
  instance. You decide when clearing is safe for your worker - e.g. once
  per iteration of a `queue:work` loop, after you've read back anything you
  needed.
- **It has no effect on the `database` driver.** It only touches the two
  in-memory singletons directly; database rows are untouched either way.
  Retention pruning (below) remains the supported mechanism for bounding
  persistent database storage.
- **It is separate from clearing the active trace context.** Clearing what
  trace/span is currently open (handled automatically, per above) and
  clearing what has already been recorded are two different concerns.
- This is not automatic eviction or a size-based policy - just an explicit
  operation you can call when you know it's safe to.

### Database storage

By default, traces and spans are held in memory for the lifetime of the
request. To persist them, publish and run the migrations (above), then set
the storage driver to `database`:

```php
// config/laravel-trace.php
'storage' => [
    'driver' => 'database',

    'database' => [
        'connection' => null, // defaults to your app's default connection
        'swallow_exceptions' => true,
    ],
],
```

A storage write failure is logged and swallowed by default, so a database
outage or a missing table never breaks the host application - set
`swallow_exceptions` to `false` while debugging your setup to let it throw
instead.

### Querying traces and spans

Once traces are being persisted you can read them back. The read API returns
plain `Trace` and `Span` objects (never Eloquent models) and works the same
against the `memory` and `database` drivers.

```php
use AdilAzhari\LaravelTrace\Facades\LaravelTrace;
use AdilAzhari\LaravelTrace\Trace\TraceStatus;
use AdilAzhari\LaravelTrace\Span\SpanType;

// Look one up by id
$trace = LaravelTrace::trace('01J9Z8...');      // ?Trace
$span  = LaravelTrace::span('01J9ZA...');        // ?Span

// Fluent query -> Illuminate collection of Trace objects
$failed = LaravelTrace::traces()
    ->whereName('http.request')
    ->whereStatus(TraceStatus::Failed)
    ->startedAfter(now()->subHour())
    ->minDurationMs(500)
    ->whereAttribute('http.method', 'POST')   // equality only
    ->orderBy('duration_ms', 'desc')
    ->get();

// Offset pagination (LengthAwarePaginator)
$page = LaravelTrace::traces()->onlyErrors()->paginate(perPage: 25);

// A trace's spans, flat (oldest first) or as a parent/child tree
$spans = LaravelTrace::spansForTrace($trace->id);
$tree  = LaravelTrace::spanTree($trace->id);   // list<SpanNode>

// Spans on their own
$slowQueries = LaravelTrace::spans()
    ->whereTrace($trace->id)
    ->whereType(SpanType::Database)
    ->minDurationMs(100)
    ->get();
```

You can also type-hint the contracts directly
(`AdilAzhari\LaravelTrace\Contracts\TraceReader` /
`SpanReader`) with an immutable `TraceQuery` / `SpanQuery`. `SpanReader` also
has a `children(SpanId $parentId): Collection` method - not exposed through
the facade or `SpanQueryBuilder` - that returns the direct children of the
given span (one level down only, not the full subtree), oldest first, on
both drivers:

```php
use AdilAzhari\LaravelTrace\Contracts\SpanReader;

$children = app(SpanReader::class)->children($span->id); // Collection<int, Span>
```

Notes and limits for this release:

- Traces and spans can be filtered by id, name, status, started-at range,
  duration range, error presence, and attribute **equality**. Attribute
  filtering compiles to a cross-database JSON path lookup; `LIKE`,
  containment, and nested attribute paths are not supported. Attribute
  *keys* are restricted to letters, digits, `_`, `-` and `.` - but only the
  `database` driver enforces this, throwing `InvalidArgumentException` for
  a key outside that set; the `memory` driver does not validate the key at
  all (it simply never matches, since nothing was ever recorded under an
  invalid key). Code that only ever ran against `memory` in tests can hit
  this for the first time against `database` in production.
- Sorting is limited to `started_at`, `finished_at`, `duration_ms`, `name`
  (and `type` for spans), always with a deterministic `id` tiebreak. The
  default order is newest first.
- Pagination is offset-based only.
- The `memory` driver only sees what the current process recorded; it does
  not persist between requests.
- There is no dashboard, HTTP API, or exporter yet.

### Pruning old traces

Traces and spans accumulate forever unless you prune them. Publish and run
the migration (above), then set a retention window:

```php
// config/laravel-trace.php
'storage' => [
    'retention' => [
        'enabled' => false, // a fresh install never deletes anything on its own
        'days' => 7,
        'chunk_size' => 500,
    ],
],
```

and run:

```bash
php artisan laravel-trace:prune
```

```
Options:
  --days=N      Prune traces started more than N days ago, overriding retention.days
  --before=DATE Prune traces started before this date/time, overriding --days
  --chunk=N     Traces deleted per batch, overriding retention.chunk_size
  --dry-run     Report exact matching counts without deleting anything
  --force       Skip the confirmation prompt
```

Notes:

- **Only completed or failed traces are pruned.** A still-`Running` trace is
  never deleted, no matter how old - handling traces abandoned by a crashed
  process is left to a future release. Age is measured from `started_at`.
- If `retention.enabled` is `false` and neither `--days` nor `--before` is
  given, the command explains that and exits successfully without deleting
  anything - safe to leave in a scheduled job while retention is off.
  Passing `--days`/`--before` explicitly always runs the prune, regardless
  of `enabled`.
- `--dry-run` runs the exact same eligibility query real pruning would and
  reports precisely what it matched, without deleting.
- A database failure during pruning is never swallowed - unlike recording,
  a failed maintenance run needs to be visible.
- Under the `memory` driver, the command explains that it has nothing to
  prune: the in-memory store belongs to the process that recorded it and
  does not persist between separate `artisan` invocations.
- **The package does not schedule this command for you.** Add it to your
  own application's scheduler, e.g. in `routes/console.php`:
  ```php
  Schedule::command('laravel-trace:prune --force')->daily();
  ```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Thank you for considering contributing to Laravel Trace! Please review our [contributing guide](.github/CONTRIBUTING.md) to get started.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Adil Azhari](https://github.com/adilazhari)
- [All Contributors](../../contributors)

## License

Laravel Trace is open-sourced software licensed under the [MIT license](LICENSE.md).
