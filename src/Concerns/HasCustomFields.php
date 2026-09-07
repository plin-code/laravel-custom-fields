<?php

declare(strict_types=1);

namespace PlinCode\CustomFields\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use PlinCode\CustomFields\Exceptions\ModelNotPersistedException;
use PlinCode\CustomFields\Exceptions\UnknownCustomFieldException;
use PlinCode\CustomFields\Facades\CustomFields;
use PlinCode\CustomFields\Models\CustomField;
use PlinCode\CustomFields\Models\CustomFieldValue;

trait HasCustomFields
{
    /** @var array<int, string> */
    private const STORAGE_COLUMNS = [
        'value_string',
        'value_text',
        'value_integer',
        'value_decimal',
        'value_boolean',
        'value_date',
        'value_datetime',
        'value_json',
    ];

    /** @return MorphMany<CustomFieldValue, $this> */
    public function customFieldValues(): MorphMany
    {
        /** @var class-string<CustomFieldValue> $model */
        $model = CustomFields::valueModel();

        return $this->morphMany($model, 'valuable');
    }

    /**
     * Reads an active definition. Pass includeInactive to read a value that
     * belongs to a definition the product has deactivated.
     */
    public function getCustomField(string $slug, bool $includeInactive = false): mixed
    {
        $field = $this->customFieldDefinition($slug, $includeInactive);
        $row = $this->customFieldValues()->where('custom_field_id', $field->getKey())->first();
        $row?->setRelation('customField', $field);

        return $row?->getValue() ?? $field->fieldType()->default();
    }

    /** @return array<string, mixed> */
    public function getCustomFields(bool $includeInactive = false): array
    {
        $definitions = $this->customFieldDefinitions($includeInactive);
        $rows = $this->customFieldRows($definitions);
        $values = [];

        foreach ($definitions as $slug => $field) {
            $values[$slug] = $rows[$slug]?->getValue() ?? $field->fieldType()->default();
        }

        return $values;
    }

    public function setCustomField(string $slug, mixed $value): void
    {
        $this->setCustomFields([$slug => $value]);
    }

    /**
     * Validates the whole batch before writing it. A null value removes the
     * stored row inside the same transaction as the rest of the batch.
     *
     * @param  array<string, mixed>  $values
     */
    public function setCustomFields(array $values, bool $complete = false): void
    {
        if (! $this->exists || $this->getKey() === null) {
            throw ModelNotPersistedException::for(static::class);
        }

        $definitions = $this->customFieldDefinitions(includeInactive: true);
        $rows = $this->customFieldRows($definitions);
        $stored = [];

        foreach ($rows as $slug => $row) {
            if ($row instanceof CustomFieldValue) {
                $stored[$slug] = $row->getValue();
            }
        }

        CustomFields::validator()->validate($this, $values, $complete, $definitions, $stored);

        /** @var class-string<CustomFieldValue> $valueModel */
        $valueModel = CustomFields::valueModel();
        $connection = $valueModel::query()->getModel()->getConnectionName();

        DB::connection($connection)->transaction(function () use ($values, $definitions, $rows): void {
            foreach ($values as $key => $value) {
                $slug = (string) $key;
                $field = $definitions[$slug];
                $row = $rows[$slug] ?? null;

                if ($value === null) {
                    $row?->delete();

                    continue;
                }

                $this->writeCustomField($field, $row, $value);
            }
        });

        $this->unsetRelation('customFieldValues');
    }

    /**
     * Removes a stored value. Definitions the product deactivated stay
     * clearable, otherwise their values could never be removed.
     */
    public function clearCustomField(string $slug): void
    {
        $field = $this->customFieldDefinition($slug, includeInactive: true);
        $row = $this->customFieldValues()->where('custom_field_id', $field->getKey())->first();

        if ($row === null) {
            return;
        }

        $row->setRelation('customField', $field);
        $row->delete();
        $this->unsetRelation('customFieldValues');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWhereCustomField(Builder $query, string $slug, mixed $value): Builder
    {
        $field = $this->customFieldDefinition($slug);
        $fieldKey = $field->getKey();
        $column = $field->fieldType()->storageColumn();

        return $query->whereHas('customFieldValues', function (Builder $inner) use ($fieldKey, $column, $value): void {
            $inner->where('custom_field_id', $fieldKey)->where($column, $value);
        });
    }

    private function writeCustomField(CustomField $field, ?CustomFieldValue $row, mixed $value): void
    {
        /** @var class-string<CustomFieldValue> $valueModel */
        $valueModel = CustomFields::valueModel();
        $row ??= $valueModel::query()->make([
            'custom_field_id' => $field->getKey(),
            'valuable_type' => $this->getMorphClass(),
            'valuable_id' => $this->getKey(),
        ]);
        $row->setRelation('customField', $field);
        $column = $field->fieldType()->storageColumn();

        foreach (self::STORAGE_COLUMNS as $storageColumn) {
            $row->setAttribute($storageColumn, $storageColumn === $column ? $field->fieldType()->serialize($value, $field) : null);
        }

        $row->save();
    }

    /**
     * Loads the definitions of the entity in a single query.
     *
     * @return array<string, CustomField>
     */
    private function customFieldDefinitions(bool $includeInactive = false): array
    {
        /** @var class-string<CustomField> $fieldModel */
        $fieldModel = CustomFields::fieldModel();
        $query = $fieldModel::query()->where('entity_type', CustomFields::entityKey($this));

        if (! $includeInactive) {
            $query->where('is_active', true);
        }

        return $query->get()
            ->keyBy(static fn (CustomField $field): string => (string) $field->getAttribute('slug'))
            ->all();
    }

    /**
     * Loads the stored rows in a single query and attaches the definitions
     * already in memory, so a read never queries a field per value.
     *
     * @param  array<string, CustomField>  $definitions
     * @return array<string, CustomFieldValue|null>
     */
    private function customFieldRows(array $definitions): array
    {
        /** @var array<string, CustomFieldValue|null> $rows */
        $rows = array_fill_keys(array_keys($definitions), null);

        if (! $this->exists || $this->getKey() === null) {
            return $rows;
        }

        $slugs = [];

        foreach ($definitions as $slug => $field) {
            $slugs[(string) $field->getKey()] = $slug;
        }

        foreach ($this->customFieldValues()->get() as $row) {
            $slug = $slugs[(string) $row->getAttribute('custom_field_id')] ?? null;

            if ($slug === null) {
                continue;
            }

            $row->setRelation('customField', $definitions[$slug]);
            $rows[$slug] = $row;
        }

        return $rows;
    }

    private function customFieldDefinition(string $slug, bool $includeInactive = false): CustomField
    {
        /** @var class-string<CustomField> $fieldModel */
        $fieldModel = CustomFields::fieldModel();
        $field = $fieldModel::query()
            ->where('entity_type', CustomFields::entityKey($this))
            ->where('slug', $slug)
            ->first();

        if ($field === null) {
            throw UnknownCustomFieldException::slug($slug, static::class);
        }

        if (! $field->getAttribute('is_active') && ! $includeInactive) {
            throw UnknownCustomFieldException::inactive($slug, static::class);
        }

        return $field;
    }
}
