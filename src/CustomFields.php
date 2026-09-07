<?php

declare(strict_types=1);

namespace PlinCode\CustomFields;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
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

    /** @param class-string<Model> $model */
    public function registerEntity(string $model, string $key, ?string $label = null): void
    {
        if (isset($this->entities[$model])) {
            return;
        }

        foreach ($this->entities as $entity) {
            if ($entity['key'] === $key) {
                throw new InvalidArgumentException("Custom field entity key [{$key}] is already registered.");
            }
        }

        $this->entities[$model] = [
            'key' => $key,
            'label' => $label ?? Str::headline(class_basename($model)),
        ];

        Relation::morphMap([$key => $model]);
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

    /**
     * Filters for every active field of the host, one per declared query operation.
     *
     * This reads the definitions from the database, so call it while serving a request
     * and not while a service provider boots.
     *
     * @return array<int, AllowedFilter>
     */
    public function filtersFor(Model|string $model): array
    {
        return $this->buildFilters($this->queryableFields($model));
    }

    /**
     * Sorts for every active field of the host that declares the sort operation.
     *
     * This reads the definitions from the database, so call it while serving a request
     * and not while a service provider boots.
     *
     * @return array<int, AllowedSort>
     */
    public function sortsFor(Model|string $model): array
    {
        return $this->buildSorts($this->queryableFields($model));
    }

    /**
     * Filters and sorts of the host, reading the definitions once.
     *
     * @return array{filters: array<int, AllowedFilter>, sorts: array<int, AllowedSort>}
     */
    public function queryOptionsFor(Model|string $model): array
    {
        $fields = $this->queryableFields($model);

        return [
            'filters' => $this->buildFilters($fields),
            'sorts' => $this->buildSorts($fields),
        ];
    }

    /** Prefix of every filter and sort name exposed to a request. */
    public function keyPrefix(): string
    {
        return (string) config('laravel-custom-fields.key_prefix', 'cf_');
    }

    /**
     * Name of the filter for a field slug and one query operation.
     *
     * Equality keeps the bare name, every other operation is suffixed with a colon,
     * a character a generated slug never contains.
     */
    public function filterName(string $slug, string $operation = CustomFieldFilter::EQUALS): string
    {
        $name = $this->keyPrefix().$slug;

        return $operation === CustomFieldFilter::EQUALS ? $name : $name.':'.$operation;
    }

    public function sortName(string $slug): string
    {
        return $this->keyPrefix().$slug;
    }

    /**
     * @param  Collection<int, Model>  $fields
     * @return array<int, AllowedFilter>
     */
    private function buildFilters(Collection $fields): array
    {
        $filters = [];

        foreach ($fields as $field) {
            /** @var CustomField $field */
            $slug = (string) $field->getAttribute('slug');

            foreach ($field->fieldType()->queryOperations() as $operation) {
                if (! CustomFieldFilter::supports($operation)) {
                    continue;
                }

                $filters[] = AllowedFilter::custom(
                    $this->filterName($slug, $operation),
                    new CustomFieldFilter($field, $operation),
                );
            }
        }

        return $filters;
    }

    /**
     * @param  Collection<int, Model>  $fields
     * @return array<int, AllowedSort>
     */
    private function buildSorts(Collection $fields): array
    {
        $sorts = [];

        foreach ($fields as $field) {
            /** @var CustomField $field */
            if (! in_array(CustomFieldSorter::SORT, $field->fieldType()->queryOperations(), true)) {
                continue;
            }

            $sorts[] = AllowedSort::custom(
                $this->sortName((string) $field->getAttribute('slug')),
                new CustomFieldSorter($field),
            );
        }

        return $sorts;
    }

    /** @return Collection<int, Model> */
    private function queryableFields(Model|string $model): Collection
    {
        $fieldModel = $this->fieldModel();

        /** @var Collection<int, Model> $fields */
        $fields = $fieldModel::query()
            ->where('entity_type', $this->entityKey($model))
            ->where('is_active', true)
            ->get();

        return $fields;
    }
}
