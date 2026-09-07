<?php

declare(strict_types=1);

namespace PlinCode\CustomFields\Query;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use InvalidArgumentException;
use PlinCode\CustomFields\Facades\CustomFields;
use PlinCode\CustomFields\Models\CustomField;
use PlinCode\EloquentSorts\Support\Direction;
use Spatie\QueryBuilder\Sorts\Sort;

/**
 * Orders hosts by the typed column of one custom field.
 *
 * The order is expressed as a correlated subquery so the select, the aggregates and
 * the ordering of the caller stay untouched and no row is duplicated. Hosts without a
 * value, and hosts whose value is null, always come last in both directions, because
 * MySQL, PostgreSQL and SQLite do not agree on where a null belongs.
 *
 * @implements Sort<Model>
 */
class CustomFieldSorter implements Sort
{
    /** Operation a field type declares when it can be ordered on. */
    public const string SORT = 'sort';

    public function __construct(private readonly Model $field)
    {
        /** @var CustomField $field */
        $field = $this->field;

        if (! in_array(self::SORT, $field->fieldType()->queryOperations(), true)) {
            throw new InvalidArgumentException(sprintf(
                'Custom field [%s] does not declare the query operation [%s].',
                (string) $field->getAttribute('slug'),
                self::SORT,
            ));
        }
    }

    public function __invoke(Builder $query, bool $descending, string $property): void
    {
        $host = $query->getModel();

        // The direction is normalised by the sibling sorts package, the same way that
        // package rejects a direction it does not know. The comparison that follows
        // only narrows the validated string back to the two literals Eloquent accepts.
        $direction = Direction::normalise($descending ? 'desc' : 'asc');

        $query->orderBy($this->presence($host), 'desc')
            ->orderBy($this->values($host), $direction === 'desc' ? 'desc' : 'asc');
    }

    /**
     * Counts the value row of the field, so a host without a value scores zero and a
     * host with a value scores one. Ordering on it first keeps the absent hosts last
     * in both directions, which the three supported databases do not agree on when a
     * correlated subquery returns null.
     */
    private function presence(Model $host): QueryBuilder
    {
        $values = $this->query($host);

        return $values
            ->selectRaw('count(*)')
            ->whereNotNull($this->column($values))
            ->toBase();
    }

    private function values(Model $host): QueryBuilder
    {
        $values = $this->query($host);

        return $values->select($this->column($values))->toBase();
    }

    /** @param  Builder<Model>  $values */
    private function column(Builder $values): string
    {
        /** @var CustomField $field */
        $field = $this->field;

        return $values->getModel()->qualifyColumn($field->fieldType()->storageColumn());
    }

    /**
     * Scopes of the configured value model are applied here, so a tenant scope keeps
     * holding on the order as it does on the read.
     *
     * @return Builder<Model>
     */
    private function query(Model $host): Builder
    {
        /** @var class-string<Model> $valueModel */
        $valueModel = CustomFields::valueModel();
        $query = $valueModel::query();
        $values = $query->getModel();

        return $query
            ->whereColumn($values->qualifyColumn('valuable_id'), $host->getQualifiedKeyName())
            ->where($values->qualifyColumn('valuable_type'), $host->getMorphClass())
            ->where($values->qualifyColumn('custom_field_id'), $this->field->getKey());
    }
}
