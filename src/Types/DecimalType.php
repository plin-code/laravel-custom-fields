<?php

declare(strict_types=1);

namespace PlinCode\CustomFields\Types;

use Illuminate\Database\Eloquent\Model;

class DecimalType extends NumberType
{
    public static function key(): string
    {
        return 'decimal';
    }

    public function storageColumn(): string
    {
        return 'value_decimal';
    }

    public function serialize(mixed $value, Model $field): mixed
    {
        return $value === null ? null : (string) $value;
    }

    public function deserialize(mixed $value, Model $field): mixed
    {
        return $value === null ? null : (string) $value;
    }

    public function rules(Model $field): array
    {
        return ['nullable', 'numeric'];
    }

    public function label(): string
    {
        return 'Decimal';
    }

    public function inputHint(): string
    {
        return 'number';
    }
}
