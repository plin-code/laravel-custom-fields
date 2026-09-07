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

    public function serialize(mixed $value, Model $field): mixed
    {
        return $value === null ? null : (bool) $value;
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
        return ['equals', 'sort'];
    }
}
