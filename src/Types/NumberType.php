<?php

declare(strict_types=1);

namespace PlinCode\CustomFields\Types;

use Illuminate\Database\Eloquent\Model;
use PlinCode\CustomFields\Contracts\FieldType;

class NumberType implements FieldType
{
    public static function key(): string
    {
        return 'number';
    }

    public function storageColumn(): string
    {
        return 'value_integer';
    }

    public function serialize(mixed $value, Model $field): mixed
    {
        return $value === null ? null : (int) $value;
    }

    public function deserialize(mixed $value, Model $field): mixed
    {
        return $value === null ? null : (int) $value;
    }

    public function rules(Model $field): array
    {
        return ['nullable', 'integer'];
    }

    public function default(): mixed
    {
        return null;
    }

    public function label(): string
    {
        return 'Number';
    }

    public function inputHint(): string
    {
        return 'number';
    }

    public function queryOperations(): array
    {
        return ['equals', 'in', 'greater_than', 'less_than', 'between', 'is_null', 'is_not_null', 'sort'];
    }
}
