<?php

declare(strict_types=1);

namespace PlinCode\CustomFields\Types;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use PlinCode\CustomFields\Contracts\FieldType;

class DateType implements FieldType
{
    public static function key(): string
    {
        return 'date';
    }

    public function storageColumn(): string
    {
        return 'value_date';
    }

    public function serialize(mixed $value, Model $field): mixed
    {
        if ($value === null) {
            return null;
        }

        return Date::parse($value)->format($this->format());
    }

    public function deserialize(mixed $value, Model $field): mixed
    {
        if ($value === null) {
            return null;
        }

        return Date::parse($value)->format($this->format());
    }

    public function rules(Model $field): array
    {
        return ['nullable', 'date'];
    }

    public function default(): mixed
    {
        return null;
    }

    public function label(): string
    {
        return 'Date';
    }

    public function inputHint(): string
    {
        return 'date';
    }

    public function queryOperations(): array
    {
        return ['equals', 'greater_than', 'less_than', 'between', 'is_null', 'is_not_null', 'sort'];
    }

    protected function format(): string
    {
        return 'Y-m-d';
    }
}
