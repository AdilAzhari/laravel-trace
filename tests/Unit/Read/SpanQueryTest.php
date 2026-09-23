<?php

declare(strict_types=1);

use AdilAzhari\LaravelTrace\Read\OrderBy;
use AdilAzhari\LaravelTrace\Read\SpanQuery;
use AdilAzhari\LaravelTrace\Span\SpanId;
use AdilAzhari\LaravelTrace\Span\SpanStatus;
use AdilAzhari\LaravelTrace\Span\SpanType;
use AdilAzhari\LaravelTrace\Trace\TraceId;

it('starts empty and unfiltered', function (): void {
    $query = SpanQuery::new();

    expect($query->ids)->toBe([])
        ->and($query->traceIds)->toBe([])
        ->and($query->parentIds)->toBe([])
        ->and($query->types)->toBe([])
        ->and($query->statuses)->toBe([])
        ->and($query->rootsOnly)->toBeNull()
        ->and($query->hasError)->toBeNull()
        ->and($query->attributes)->toBe([]);
});

it('is immutable: a where-clause returns a new instance', function (): void {
    $base = SpanQuery::new();
    $filtered = $base->whereType(SpanType::Database);

    expect($filtered)->not->toBe($base)
        ->and($base->types)->toBe([])
        ->and($filtered->types)->toBe([SpanType::Database]);
});

it('accepts trace and parent ids as value objects or strings', function (): void {
    $traceId = TraceId::generate();
    $parentId = SpanId::generate();

    $query = SpanQuery::new()
        ->whereTrace($traceId)
        ->whereParent($parentId, '01LITERAL');

    expect($query->traceIds)->toBe([$traceId->value])
        ->and($query->parentIds)->toBe([$parentId->value, '01LITERAL']);
});

it('captures the roots-only filter', function (): void {
    expect(SpanQuery::new()->onlyRoots()->rootsOnly)->toBeTrue()
        ->and(SpanQuery::new()->rootsOnly)->toBeNull();
});

it('captures status and error filters', function (): void {
    $query = SpanQuery::new()
        ->whereStatus(SpanStatus::Failed, SpanStatus::Running)
        ->onlyErrors();

    expect($query->statuses)->toBe([SpanStatus::Failed, SpanStatus::Running])
        ->and($query->hasError)->toBeTrue();
});

it('orders by started_at descending by default', function (): void {
    expect(SpanQuery::new()->orderBy)->toEqual(new OrderBy('started_at', 'desc'));
});

it('rejects a sort field that is not a real column', function (): void {
    expect(fn () => SpanQuery::new()->orderByField('sql'))
        ->toThrow(InvalidArgumentException::class);
});

it('accepts span-specific sort fields', function (): void {
    expect(SpanQuery::new()->orderByField('type', 'asc')->orderBy)
        ->toEqual(new OrderBy('type', 'asc'));
});

it('is a final readonly class', function (): void {
    $reflection = new ReflectionClass(SpanQuery::class);

    expect($reflection->isFinal())->toBeTrue()
        ->and($reflection->isReadOnly())->toBeTrue();
});

it('rejects direct assignment to a readonly property', function (): void {
    $query = SpanQuery::new();

    expect(fn () => $query->types = [SpanType::Database])
        ->toThrow(Error::class, 'Cannot modify readonly property');
});

it('rejects indirect mutation of a readonly array property', function (): void {
    $query = SpanQuery::new()->whereType(SpanType::Database);

    expect(fn () => $query->types[] = SpanType::Listener)
        ->toThrow(Error::class, 'modify readonly property '.SpanQuery::class.'::$types');

    // The rejected mutation attempt must not have partially applied.
    expect($query->types)->toBe([SpanType::Database]);
});

it('keeps every earlier instance in a chain unchanged as later calls are made', function (): void {
    $empty = SpanQuery::new();
    $withType = $empty->whereType(SpanType::Database);
    $withTypeAndRoots = $withType->onlyRoots();
    $withTypeAndRootsAndErrors = $withTypeAndRoots->onlyErrors();

    expect($empty->types)->toBe([])
        ->and($empty->rootsOnly)->toBeNull()
        ->and($empty->hasError)->toBeNull()
        ->and($withType->types)->toBe([SpanType::Database])
        ->and($withType->rootsOnly)->toBeNull()
        ->and($withType->hasError)->toBeNull()
        ->and($withTypeAndRoots->types)->toBe([SpanType::Database])
        ->and($withTypeAndRoots->rootsOnly)->toBeTrue()
        ->and($withTypeAndRoots->hasError)->toBeNull()
        ->and($withTypeAndRootsAndErrors->types)->toBe([SpanType::Database])
        ->and($withTypeAndRootsAndErrors->rootsOnly)->toBeTrue()
        ->and($withTypeAndRootsAndErrors->hasError)->toBeTrue()
        ->and($withTypeAndRootsAndErrors)->not->toBe($withTypeAndRoots)
        ->and($withTypeAndRoots)->not->toBe($withType)
        ->and($withType)->not->toBe($empty);
});
