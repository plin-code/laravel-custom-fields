<?php

declare(strict_types=1);

namespace PlinCode\CustomFields\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use PlinCode\CustomFields\Models\CustomFieldValue;

final class CustomFieldValueSaved implements ShouldDispatchAfterCommit
{
    public function __construct(public readonly CustomFieldValue $value) {}
}
