<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Read;

use AdilAzhari\LaravelTrace\Span\Span;

/**
 * One node in a {@see SpanTree}: a span plus its direct child nodes,
 * ordered oldest first.
 *
 * A read-side view built from a flat span list. The hierarchy is not stored
 * as nesting — {@see Span} keeps only a `parentId` — so it is recomputed
 * here on demand and never persisted.
 */
final readonly class SpanNode
{
    /**
     * @param  list<SpanNode>  $children
     */
    public function __construct(
        public Span $span,
        public array $children = [],
    ) {}
}
