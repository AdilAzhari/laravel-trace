<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Read;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Adds a single equality condition on a JSON `attributes` key to a query.
 *
 * Equality only, and cross-database safe: it compiles to
 * `json_extract(attributes, '$."<key>"')` on SQLite, the `->>'<key>'`
 * accessor on MySQL/MariaDB and Postgres. No `LIKE`, containment, or
 * nested paths. A `null` value matches rows where the key is JSON `null`
 * or absent (the database cannot tell those apart, so neither does this).
 *
 * The key is a JSON path segment interpolated into SQL, never a bound
 * value, so it is validated against a conservative character set first.
 */
final class AttributeFilter
{
    private const string KEY_PATTERN = '/^[A-Za-z0-9_.\-]+$/';

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $builder
     */
    public static function apply(Builder $builder, string $key, string|int|float|bool|null $value): void
    {
        if (! preg_match(self::KEY_PATTERN, $key)) {
            throw new InvalidArgumentException(sprintf(
                'Cannot filter by attribute key [%s]: only letters, digits, "_", "-" and "." are supported.',
                $key,
            ));
        }

        $path = 'attributes->'.$key;

        if ($value === null) {
            $builder->whereNull($path);

            return;
        }

        $builder->where($path, $value);
    }
}
