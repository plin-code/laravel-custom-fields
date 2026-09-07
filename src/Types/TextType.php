<?php

declare(strict_types=1);

namespace PlinCode\CustomFields\Types;

use Illuminate\Database\Eloquent\Model;
use PlinCode\CustomFields\Contracts\FieldType;

class TextType implements FieldType
{
    public static function key(): string
    {
        return 'text';
    }

    public function storageColumn(): string
    {
        return 'value_string';
    }

    public function serialize(mixed $value, Model $field): mixed
    {
        return $value;
    }

    public function deserialize(mixed $value, Model $field): mixed
    {
        return $value;
    }

    public function rules(Model $field): array
    {
        return ['nullable', 'string', 'max:255'];
    }

    public function default(): mixed
    {
        return null;
    }

    public function label(): string
    {
        return 'Text';
    }

    public function inputHint(): string
    {
        return 'text';
    }

    public function queryOperations(): array
    {
        return ['equals', 'in', 'contains', 'is_null', 'is_not_null', 'sort'];
    }
}
