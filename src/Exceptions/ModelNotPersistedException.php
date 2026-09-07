<?php

declare(strict_types=1);

namespace PlinCode\CustomFields\Exceptions;

use RuntimeException;

class ModelNotPersistedException extends RuntimeException
{
    /** @param class-string $model */
    public static function for(string $model): self
    {
        return new self("Custom field values require a persisted [{$model}] instance.");
    }
}
