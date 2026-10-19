<?php

declare(strict_types=1);

namespace PlinCode\CustomFields\Types;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;

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

    public function serialize(mixed $value, Model $field): mixed
    {
        if ($value === null) {
            return null;
        }

        return Date::parse($value, config('app.timezone'))->setTimezone(config('app.timezone'))->format($this->format());
    }

    public function deserialize(mixed $value, Model $field): mixed
    {
        if ($value === null) {
            return null;
        }

        return Date::parse($value, config('app.timezone'))->setTimezone(config('app.timezone'))->format($this->format());
    }

    public function label(): string
    {
        return 'Date and time';
    }

    public function inputHint(): string
    {
        return 'datetime-local';
    }

    protected function format(): string
    {
        return 'Y-m-d H:i:s';
    }
}
