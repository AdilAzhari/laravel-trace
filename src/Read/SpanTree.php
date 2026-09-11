<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Read;

use AdilAzhari\LaravelTrace\Contracts\SpanReader;
use AdilAzhari\LaravelTrace\Span\Span;

/**
 * Builds the parent/child tree for a set of spans from their flat
 * `parentId` pointers.
 *
 * Pure and storage-agnostic: give it the spans a {@see SpanReader}
 * returned and it returns the root {@see SpanNode}s. A span is a root when
 * it has no parent, or when its parent is not among the spans given (a
 * partial slice, or a span whose parent lives in another service). Children
 * and roots are ordered oldest first.
 */
final class SpanTree
{
    /**
     * @param  iterable<Span>  $spans
     * @return list<SpanNode>
     */
    public static function fromSpans(iterable $spans): array
    {
        /** @var array<string, Span> $byId */
        $byId = [];

        /** @var array<string, list<Span>> $childrenByParent */
        $childrenByParent = [];

        foreach ($spans as $span) {
            $byId[$span->id->value] = $span;
        }

        $roots = [];

        foreach ($byId as $span) {
            $parentId = $span->parentId?->value;

            if ($parentId !== null && isset($byId[$parentId])) {
                $childrenByParent[$parentId][] = $span;

                continue;
            }

            $roots[] = $span;
        }

        return self::nodes($roots, $childrenByParent);
    }

    /**
     * @param  list<Span>  $spans
     * @param  array<string, list<Span>>  $childrenByParent
     * @return list<SpanNode>
     */
    private static function nodes(array $spans, array $childrenByParent): array
    {
        usort($spans, static fn (Span $a, Span $b): int => $a->startedAt <=> $b->startedAt);

        return array_map(
            static fn (Span $span): SpanNode => new SpanNode(
                $span,
                self::nodes($childrenByParent[$span->id->value] ?? [], $childrenByParent),
            ),
            $spans,
        );
    }
}
