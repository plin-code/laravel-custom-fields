<?php

declare(strict_types=1);

namespace PlinCode\CustomFields\Events;

use PlinCode\CustomFields\Models\CustomFieldValue;

final class CustomFieldValueDeleted
{
    public function __construct(public readonly CustomFieldValue $value) {}
}
