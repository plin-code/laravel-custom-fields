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

    /** @return array<int, string> */
    public function queryOperations(): array;
}
