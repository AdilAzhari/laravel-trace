<?php

declare(strict_types=1);

use AdilAzhari\LaravelTrace\Read\SpanNode;
use AdilAzhari\LaravelTrace\Read\SpanTree;
use AdilAzhari\LaravelTrace\Span\SpanId;
use AdilAzhari\LaravelTrace\Span\SpanType;
use AdilAzhari\LaravelTrace\Trace\TraceId;

it('returns an empty list when given no spans', function (): void {
    expect(SpanTree::fromSpans([]))->toBe([]);
});

it('returns every span as a root when none has a parent', function (): void {
    $traceId = TraceId::generate();
    $a = makeSpan($traceId, 'a', startedAt: new DateTimeImmutable('2026-01-01 12:00:01'));
    $b = makeSpan($traceId, 'b', startedAt: new DateTimeImmutable('2026-01-01 12:00:02'));

    $roots = SpanTree::fromSpans([$b, $a]);

    expect($roots)->toHaveCount(2)
        ->and($roots[0])->toBeInstanceOf(SpanNode::class)
        ->and($roots[0]->span->name)->toBe('a')
        ->and($roots[1]->span->name)->toBe('b')
        ->and($roots[0]->children)->toBe([]);
});

it('nests children under their parent ordered oldest first', function (): void {
    $traceId = TraceId::generate();
    $root = makeSpan($traceId, 'root', SpanType::Http, startedAt: new DateTimeImmutable('2026-01-01 12:00:00'));
    $childLate = makeSpan($traceId, 'child-late', parentId: $root->id, startedAt: new DateTimeImmutable('2026-01-01 12:00:02'));
    $childEarly = makeSpan($traceId, 'child-early', parentId: $root->id, startedAt: new DateTimeImmutable('2026-01-01 12:00:01'));
    $grandchild = makeSpan($traceId, 'grandchild', parentId: $childEarly->id, startedAt: new DateTimeImmutable('2026-01-01 12:00:03'));

    $roots = SpanTree::fromSpans([$grandchild, $childLate, $root, $childEarly]);

    expect($roots)->toHaveCount(1)
        ->and($roots[0]->span->name)->toBe('root')
        ->and(array_map(fn (SpanNode $n): string => $n->span->name, $roots[0]->children))
        ->toBe(['child-early', 'child-late'])
        ->and($roots[0]->children[0]->children[0]->span->name)->toBe('grandchild');
});

it('treats a span whose parent is not in the set as a root', function (): void {
    $traceId = TraceId::generate();
    $orphan = makeSpan($traceId, 'orphan', parentId: SpanId::generate());

    $roots = SpanTree::fromSpans([$orphan]);

    expect($roots)->toHaveCount(1)
        ->and($roots[0]->span->name)->toBe('orphan');
});
