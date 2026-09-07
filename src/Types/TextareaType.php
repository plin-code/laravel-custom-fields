<?php

declare(strict_types=1);

namespace PlinCode\CustomFields\Types;

use Illuminate\Database\Eloquent\Model;

class TextareaType extends TextType
{
    public static function key(): string
    {
        return 'textarea';
    }

    public function storageColumn(): string
    {
        return 'value_text';
    }

    public function rules(Model $field): array
    {
        return ['nullable', 'string'];
    }

    public function label(): string
    {
        return 'Textarea';
    }

    public function inputHint(): string
    {
        return 'textarea';
    }

    public function queryOperations(): array
    {
        return ['equals', 'contains', 'is_null', 'is_not_null'];
    }
}
