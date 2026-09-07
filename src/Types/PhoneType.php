<?php

declare(strict_types=1);

namespace PlinCode\CustomFields\Types;

class PhoneType extends TextType
{
    public static function key(): string
    {
        return 'phone';
    }

    public function label(): string
    {
        return 'Phone';
    }
}
