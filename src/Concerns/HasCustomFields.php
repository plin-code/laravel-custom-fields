<?php

declare(strict_types=1);

namespace PlinCode\CustomFields\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use InvalidArgumentException;
use PlinCode\CustomFields\Facades\CustomFields;

trait HasCustomFields
{
    public function customFieldValues(): MorphMany
    {
        return $this->morphMany(CustomFields::valueModel(), 'valuable');
    }

    public function getCustomField(string $slug): mixed
    {
        $field = $this->customFieldDefinition($slug);
        $value = $this->customFieldValues()->where('custom_field_id', $field->getKey())->first();

        return $value?->getValue() ?? $field->fieldType()->default();
    }

    /** @return array<string, mixed> */
    public function getCustomFields(bool $includeInactive = false): array
    {
        $entityKey = CustomFields::entityKey($this);
        $fieldModel = CustomFields::fieldModel();
        $fields = $fieldModel::query()->where('entity_type', $entityKey)
            ->when(! $includeInactive, fn (Builder $query): Builder => $query->where('is_active', true))
            ->get();
        $values = $this->customFieldValues()->with('customField')->get()->keyBy('custom_field_id');

        return $fields->mapWithKeys(function ($field) use ($values): array {
            $value = $values->get($field->getKey());

            return [$field->slug => $value?->getValue() ?? $field->fieldType()->default()];
        })->all();
    }

    public function setCustomField(string $slug, mixed $value): void
    {
        $field = $this->customFieldDefinition($slug);
        $model = CustomFields::valueModel();
        $row = $model::query()->firstOrNew([
            'custom_field_id' => $field->getKey(),
            'valuable_type' => $this->getMorphClass(),
            'valuable_id' => $this->getKey(),
        ]);
        $row->setRelation('customField', $field);
        $column = $field->fieldType()->storageColumn();

        foreach (['value_string', 'value_text', 'value_integer', 'value_decimal', 'value_boolean', 'value_date', 'value_datetime', 'value_json'] as $storageColumn) {
            $row->setAttribute($storageColumn, $storageColumn === $column ? $field->fieldType()->serialize($value, $field) : null);
        }

        $row->save();
    }

    public function scopeWhereCustomField(Builder $query, string $slug, mixed $value): Builder
    {
        $field = $this->customFieldDefinition($slug);

        return $query->whereHas('customFieldValues', function (Builder $inner) use ($field, $value): void {
            $inner->where('custom_field_id', $field->getKey())
                ->where($field->fieldType()->storageColumn(), $value);
        });
    }

    private function customFieldDefinition(string $slug): object
    {
        $field = CustomFields::fieldModel()::query()
            ->where('entity_type', CustomFields::entityKey($this))
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if ($field === null) {
            throw new InvalidArgumentException("Custom field [{$slug}] is not defined for this model.");
        }

        return $field;
    }
}
