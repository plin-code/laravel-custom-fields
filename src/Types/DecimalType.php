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
        return $value === null ? null : ltrim((string) $value, '+');
    }

    /**
     * MySQL and PostgreSQL hand back the full scale of the decimal column while
     * SQLite returns what was written, so the fraction is trimmed and the same
     * stored value reads the same on every driver. A legacy value stored in
     * exponent form is returned as stored, since trimming it would change it.
     */
    public function deserialize(mixed $value, Model $field): mixed
    {
        if ($value === null) {
            return null;
        }

        $value = (string) $value;

        if (! str_contains($value, '.') || str_contains(strtolower($value), 'e')) {
            return $value;
        }

        return rtrim(rtrim($value, '0'), '.');
    }

    public function rules(Model $field): array
    {
        return ['nullable', 'numeric', 'decimal:0,6', 'between:-99999999999999.999999,99999999999999.999999'];
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
