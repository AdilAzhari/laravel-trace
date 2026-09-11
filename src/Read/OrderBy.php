<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Read;

use InvalidArgumentException;

/**
 * A single primary sort: a column name plus a direction.
 *
 * The column is validated by the query object that produces the
 * {@see OrderBy} (each aggregate knows its own sortable columns); this
 * value object only guards the direction.
 */
final readonly class OrderBy
{
    /** @var 'asc'|'desc' */
    public string $direction;

    public function __construct(
        public string $field,
        string $direction = 'desc',
    ) {
        $direction = strtolower($direction);

        if ($direction !== 'asc' && $direction !== 'desc') {
            throw new InvalidArgumentException(sprintf(
                'Order direction must be "asc" or "desc", got [%s].',
                $direction,
            ));
        }

        $this->direction = $direction;
    }

    public function isAscending(): bool
    {
        return $this->direction === 'asc';
    }
}
