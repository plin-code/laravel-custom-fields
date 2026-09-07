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
     * The vocabulary of the package is equals, in, contains, greater_than, less_than,
     * between, is_null, is_not_null, contains_any, contains_all and sort. A consumer
     * type may declare its own operation, and then it also provides the filter for it.
     *
     * @return array<int, string>
     */
    public function queryOperations(): array;
}
