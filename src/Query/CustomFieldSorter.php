<?php

declare(strict_types=1);

namespace PlinCode\CustomFields\Query;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use PlinCode\CustomFields\Facades\CustomFields;
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
        $column = $field->fieldType()->storageColumn();
        $valueModel = CustomFields::valueModel();
        $values = $valueModel::query()
            ->select($valueModel::query()->getModel()->qualifyColumn($column))
            ->whereColumn(
                $valueModel::query()->getModel()->qualifyColumn('valuable_id'),
                $model->getQualifiedKeyName(),
            )
            ->where($valueModel::query()->getModel()->qualifyColumn('valuable_type'), $model->getMorphClass())
            ->where($valueModel::query()->getModel()->qualifyColumn('custom_field_id'), $field->getKey());

        $query->orderBy($values, $descending ? 'desc' : 'asc');
    }
}
