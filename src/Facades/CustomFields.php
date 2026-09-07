<?php

declare(strict_types=1);

namespace PlinCode\CustomFields\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \PlinCode\CustomFields\CustomFields
 */
class CustomFields extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \PlinCode\CustomFields\CustomFields::class;
    }
}
