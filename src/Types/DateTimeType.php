<?php

declare(strict_types=1);

namespace PlinCode\CustomFields\Types;

class DateTimeType extends DateType
{
    public static function key(): string
    {
        return 'datetime';
    }

    public function storageColumn(): string
    {
        return 'value_datetime';
    }

    public function label(): string
    {
        return 'Date and time';
    }

    public function inputHint(): string
    {
        return 'datetime-local';
    }
}
