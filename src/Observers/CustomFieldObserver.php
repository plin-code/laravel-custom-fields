<?php

declare(strict_types=1);

namespace PlinCode\CustomFields\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PlinCode\CustomFields\Facades\CustomFields;

class CustomFieldObserver
{
    public function creating(Model $field): void
    {
        $name = trim(mb_strtolower((string) $field->getAttribute('name')));

        if ($name === '') {
            throw new InvalidArgumentException('A custom field name cannot be empty.');
        }

        $field->setAttribute('name', $name);
        $field->setAttribute('slug', $field->getAttribute('slug') ?: $this->slug($name, (string) $field->getAttribute('entity_type')));
        $this->validateOptions($field);
    }

    public function updating(Model $field): void
    {
        if ($field->isDirty('slug') || $field->isDirty('entity_type')) {
            throw new InvalidArgumentException('A custom field slug and entity cannot be changed.');
        }

        $valueModel = CustomFields::valueModel();

        if ($field->isDirty('type') && $valueModel::query()->where('custom_field_id', $field->getKey())->exists()) {
            throw new InvalidArgumentException('A custom field type cannot change while values exist.');
        }

        $name = trim(mb_strtolower((string) $field->getAttribute('name')));

        if ($name === '') {
            throw new InvalidArgumentException('A custom field name cannot be empty.');
        }

        $field->setAttribute('name', $name);
        $this->preventOptionRemoval($field);
        $this->validateOptions($field);
    }

    private function preventOptionRemoval(Model $field): void
    {
        $original = array_map(
            static fn (array $option): string => (string) ($option['key'] ?? ''),
            (array) $field->getOriginal('options'),
        );
        $current = array_map(
            static fn (array $option): string => (string) ($option['key'] ?? ''),
            (array) $field->getAttribute('options'),
        );

        foreach ($original as $key) {
            if (! in_array($key, $current, true)) {
                throw new InvalidArgumentException("Custom field option [{$key}] cannot be removed.");
            }
        }
    }

    private function validateOptions(Model $field): void
    {
        $seen = [];

        foreach ((array) $field->getAttribute('options') as $option) {
            if (! is_array($option) || trim((string) ($option['key'] ?? '')) === '' || trim((string) ($option['label'] ?? '')) === '') {
                throw new InvalidArgumentException('Custom field options require a key and label.');
            }

            $key = (string) $option['key'];

            if (isset($seen[$key])) {
                throw new InvalidArgumentException("Custom field option [{$key}] is duplicated.");
            }

            $seen[$key] = true;
        }
    }

    private function slug(string $name, string $entityType): string
    {
        $base = Str::slug($name) ?: 'field';
        $base = mb_substr($base, 0, 100);
        $slug = $base;
        $suffix = 2;
        $model = CustomFields::fieldModel();

        while ($model::query()->where('entity_type', $entityType)->where('slug', $slug)->exists()) {
            $suffixText = '-'.$suffix++;
            $trimmed = rtrim(mb_substr($base, 0, max(1, 100 - mb_strlen($suffixText))), '-');
            $slug = mb_substr($trimmed.$suffixText, 0, 100);
        }

        return $slug;
    }
}
