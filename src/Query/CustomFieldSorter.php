<?php

declare(strict_types=1);

namespace PlinCode\CustomFields\Query;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\JoinClause;
use PlinCode\CustomFields\Models\CustomField;
use Spatie\QueryBuilder\Sorts\Sort;

/** @implements Sort<Model> */
class CustomFieldSorter implements Sort
{
    public function __construct(
        private readonly Model $field,
    ) {}

    public function __invoke(Builder $query, bool $descending, string $property): void
    {
        /** @var CustomField $field */
        $field = $this->field;
        $model = $query->getModel();
        $alias = 'custom_field_'.$field->getKey();
        $valuesTable = (string) config('laravel-custom-fields.tables.values');
        $column = $field->fieldType()->storageColumn();

        $query->leftJoin($valuesTable.' as '.$alias, function (JoinClause $join) use ($alias, $model): void {
            $join->on($alias.'.valuable_id', '=', $model->getQualifiedKeyName())
                ->where($alias.'.valuable_type', '=', $model->getMorphClass())
                ->where($alias.'.custom_field_id', '=', $field->getKey());
        });

        $query->orderBy($alias.'.'.$column, $descending ? 'desc' : 'asc');
    }
}
