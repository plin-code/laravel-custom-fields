<?php

declare(strict_types=1);

namespace PlinCode\CustomFields\Contracts;

use Illuminate\Database\Eloquent\Model;

interface FieldType
{
    public static function key(): string;

    public function storageColumn(): string;

    public function serialize(mixed $value, Model $field): mixed;

    public function deserialize(mixed $value, Model $field): mixed;

    /** @return array<int, mixed> */
    public function rules(Model $field): array;

    public function default(): mixed;

    public function label(): string;

    public function inputHint(): string;

    /**
     * Query operations the type can actually serve.
     *
     * The filter operations are equals, in, contains, greater_than, less_than, between,
     * is_null, is_not_null, contains_any and contains_all, and sort makes the field
     * sortable. Any other entry is skipped by CustomFields::filtersFor(), so a consumer
     * type that needs its own operation registers its own AllowedFilter for it.
     *
     * @return array<int, string>
     */
    public function queryOperations(): array;
}
