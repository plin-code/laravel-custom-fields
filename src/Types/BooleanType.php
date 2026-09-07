<?php

declare(strict_types=1);

namespace PlinCode\CustomFields\Types;

use Illuminate\Database\Eloquent\Model;
use PlinCode\CustomFields\Contracts\FieldType;

class BooleanType implements FieldType
{
    public static function key(): string
    {
        return 'boolean';
    }

    public function storageColumn(): string
    {
        return 'value_boolean';
    }

    /**
     * A request carries a boolean as text, so the textual forms are read as well.
     * Validated writes only ever pass true, false, 1, 0, "1" and "0", which keep the
     * same meaning they had before.
     */
    public function serialize(mixed $value, Model $field): mixed
    {
        if ($value === null) {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value;
    }

    public function deserialize(mixed $value, Model $field): mixed
    {
        return $value === null ? null : (bool) $value;
    }

    public function rules(Model $field): array
    {
        return ['nullable', 'boolean'];
    }

    public function default(): mixed
    {
        return null;
    }

    public function label(): string
    {
        return 'Boolean';
    }

    public function inputHint(): string
    {
        return 'checkbox';
    }

    public function queryOperations(): array
    {
        return ['equals', 'is_null', 'is_not_null', 'sort'];
    }
}
