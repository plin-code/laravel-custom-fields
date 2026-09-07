<?php

declare(strict_types=1);

namespace PlinCode\CustomFields\Types;

use Illuminate\Database\Eloquent\Model;
use PlinCode\CustomFields\Contracts\FieldType;

class MultiSelectType implements FieldType
{
    public static function key(): string
    {
        return 'multiselect';
    }

    public function storageColumn(): string
    {
        return 'value_json';
    }

    public function serialize(mixed $value, Model $field): mixed
    {
        return array_values((array) $value);
    }

    public function deserialize(mixed $value, Model $field): mixed
    {
        return is_array($value) ? $value : [];
    }

    public function rules(Model $field): array
    {
        return ['nullable', 'array'];
    }

    public function default(): mixed
    {
        return [];
    }

    public function label(): string
    {
        return 'Multiple select';
    }

    public function inputHint(): string
    {
        return 'multiselect';
    }

    public function queryOperations(): array
    {
        return ['contains_any', 'contains_all'];
    }
}
