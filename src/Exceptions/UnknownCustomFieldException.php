<?php

declare(strict_types=1);

namespace PlinCode\CustomFields\Exceptions;

use InvalidArgumentException;

class UnknownCustomFieldException extends InvalidArgumentException
{
    /** @param class-string $model */
    public static function slug(string $slug, string $model): self
    {
        return new self("Custom field [{$slug}] is not defined for model [{$model}].");
    }

    /** @param class-string $model */
    public static function inactive(string $slug, string $model): self
    {
        return new self("Custom field [{$slug}] is not active for model [{$model}].");
    }
}
