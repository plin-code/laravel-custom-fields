<?php

declare(strict_types=1);

namespace PlinCode\CustomFields\Events;

use PlinCode\CustomFields\Models\CustomField;

final class CustomFieldCreated
{
    public function __construct(public readonly CustomField $field) {}
}
