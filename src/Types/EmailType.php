<?php

declare(strict_types=1);

namespace PlinCode\CustomFields\Types;

use Illuminate\Database\Eloquent\Model;

class EmailType extends TextType
{
    public static function key(): string
    {
        return 'email';
    }

    public function rules(Model $field): array
    {
        return ['nullable', 'email', 'max:255'];
    }

    public function label(): string
    {
        return 'Email';
    }
}
