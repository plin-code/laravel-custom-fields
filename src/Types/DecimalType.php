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

    /**
     * MySQL and PostgreSQL hand back the full scale of the decimal column while
     * SQLite returns what was written, so the fraction is trimmed and the same
     * stored value reads the same on every driver.
     */
    public function deserialize(mixed $value, Model $field): mixed
    {
        if ($value === null) {
            return null;
        }

        $value = (string) $value;

        return str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value;
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
