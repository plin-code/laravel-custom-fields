<?php

declare(strict_types=1);

namespace PlinCode\CustomFields\Types;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use PlinCode\CustomFields\Contracts\FieldType;

class SelectType implements FieldType
{
    public static function key(): string
    {
        return 'select';
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
        return ['nullable', 'string', Rule::in($this->optionKeys($field))];
    }

    public function default(): mixed
    {
        return null;
    }

    public function label(): string
    {
        return 'Select';
    }

    public function inputHint(): string
    {
        return 'select';
    }

    public function queryOperations(): array
    {
        return ['equals', 'in', 'sort'];
    }

    /** @return array<int, string> */
    private function optionKeys(Model $field): array
    {
        return array_values(array_filter(array_map(
            static fn (mixed $option): ?string => is_array($option) && isset($option['key']) ? (string) $option['key'] : null,
            $field->getAttribute('options') ?? [],
        )));
    }
}
