<?php

declare(strict_types=1);

namespace PlinCode\CustomFields\Query;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use PlinCode\CustomFields\Models\CustomField;
use Spatie\QueryBuilder\Filters\Filter;

/** @implements Filter<Model> */
class CustomFieldFilter implements Filter
{
    public function __construct(private readonly Model $field) {}

    public function __invoke(Builder $query, mixed $value, string $property): void
    {
        /** @var CustomField $field */
        $field = $this->field;
        $column = $field->fieldType()->storageColumn();
        $fieldId = $field->getKey();

        $query->whereHas('customFieldValues', function (Builder $inner) use ($column, $fieldId, $value): void {
            $inner->where('custom_field_id', $fieldId);

            if (is_array($value)) {
                $inner->whereIn($column, $value);

                return;
            }

            $inner->where($column, $value);
        });
    }
}
