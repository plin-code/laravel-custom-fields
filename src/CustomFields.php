<?php

declare(strict_types=1);

namespace PlinCode\CustomFields;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PlinCode\CustomFields\Contracts\FieldType;
use PlinCode\CustomFields\Models\CustomField;
use PlinCode\CustomFields\Query\CustomFieldFilter;
use PlinCode\CustomFields\Query\CustomFieldSorter;
use PlinCode\CustomFields\Validation\ValueValidator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class CustomFields
{
    /** @var array<string, class-string<FieldType>> */
    private array $types = [];

    /** @var array<string, array{key: string, label: string}> */
    private array $entities = [];

    public function registerType(string $type): void
    {
        $key = $type::key();

        if (isset($this->types[$key])) {
            throw new InvalidArgumentException("Custom field type [{$key}] is already registered.");
        }

        /** @var class-string<FieldType> $type */
        $this->types[$key] = $type;
    }

    public function type(string $key): FieldType
    {
        if (! isset($this->types[$key])) {
            throw new InvalidArgumentException("Unknown custom field type [{$key}].");
        }

        return app($this->types[$key]);
    }

    /** @return array<string, FieldType> */
    public function types(): array
    {
        return array_map(fn (string $type): FieldType => app($type), $this->types);
    }

    public function registerEntity(string $model, string $key, ?string $label = null): void
    {
        if (in_array($key, array_column($this->entities, 'key'), true)) {
            throw new InvalidArgumentException("Custom field entity key [{$key}] is already registered.");
        }

        $this->entities[$model] = [
            'key' => $key,
            'label' => $label ?? Str::headline(class_basename($model)),
        ];
    }

    /** @return array<string, array{key: string, label: string}> */
    public function entities(): array
    {
        return $this->entities;
    }

    public function entityKey(Model|string $model): string
    {
        $class = $model instanceof Model ? $model::class : $model;

        if (! isset($this->entities[$class])) {
            throw new InvalidArgumentException("Model [{$class}] is not registered for custom fields.");
        }

        return $this->entities[$class]['key'];
    }

    public function fieldModel(): string
    {
        return config('laravel-custom-fields.models.custom_field');
    }

    public function valueModel(): string
    {
        return config('laravel-custom-fields.models.custom_field_value');
    }

    public function validator(): ValueValidator
    {
        return app(ValueValidator::class);
    }

    /** @param array<string, mixed> $values */
    public function validate(Model $model, array $values, bool $complete = false): void
    {
        $this->validator()->validate($model, $values, $complete);
    }

    /** @return array<int, AllowedFilter> */
    public function filtersFor(Model|string $model): array
    {
        $fieldModel = $this->fieldModel();
        $entityKey = $this->entityKey($model);

        return $fieldModel::query()->where('entity_type', $entityKey)->where('is_active', true)->get()
            ->map(fn (Model $field): AllowedFilter => AllowedFilter::custom(
                'cf_'.$field->getAttribute('slug'),
                new CustomFieldFilter($field),
            ))->all();
    }

    /** @return array<int, AllowedSort> */
    public function sortsFor(Model|string $model): array
    {
        $fieldModel = $this->fieldModel();
        $entityKey = $this->entityKey($model);

        return $fieldModel::query()->where('entity_type', $entityKey)->where('is_active', true)->get()
            ->filter(function (Model $field): bool {
                /** @var CustomField $field */
                return in_array('sort', $field->fieldType()->queryOperations(), true);
            })
            ->map(fn (Model $field): AllowedSort => AllowedSort::custom(
                'cf_'.$field->getAttribute('slug'),
                new CustomFieldSorter($field),
            ))->all();
    }
}
