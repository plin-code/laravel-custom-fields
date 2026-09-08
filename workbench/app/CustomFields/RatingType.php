<?php

declare(strict_types=1);

namespace Workbench\App\CustomFields;

use Illuminate\Database\Eloquent\Model;
use PlinCode\CustomFields\Contracts\FieldType;

/**
 * A field type defined by a consumer of the package.
 *
 * It brings its own validation rules, its own serialization, one of the eight
 * storage columns and its own query operation, without any change to the core.
 */
class RatingType implements FieldType
{
    /** The operation this type adds to the vocabulary of the package. */
    public const string WITHIN_REACH = 'within_reach';

    public static function key(): string
    {
        return 'rating';
    }

    public function storageColumn(): string
    {
        return 'value_integer';
    }

    public function serialize(mixed $value, Model $field): mixed
    {
        return $value === null ? null : (int) round((float) $value);
    }

    public function deserialize(mixed $value, Model $field): mixed
    {
        return $value === null ? null : (int) $value.' stars';
    }

    public function rules(Model $field): array
    {
        return ['nullable', 'integer', 'between:1,5'];
    }

    public function default(): mixed
    {
        return '0 stars';
    }

    public function label(): string
    {
        return 'Rating';
    }

    public function inputHint(): string
    {
        return 'number';
    }

    public function queryOperations(): array
    {
        return ['equals', 'between', 'sort', self::WITHIN_REACH];
    }
}
