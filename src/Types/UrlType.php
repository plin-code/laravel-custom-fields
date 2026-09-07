<?php

declare(strict_types=1);

namespace PlinCode\CustomFields\Types;

use Illuminate\Database\Eloquent\Model;

class UrlType extends TextType
{
    public static function key(): string
    {
        return 'url';
    }

    public function rules(Model $field): array
    {
        return ['nullable', 'url', 'max:255'];
    }

    public function label(): string
    {
        return 'URL';
    }
}
