<?php

declare(strict_types=1);

namespace PlinCode\CustomFields\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use PlinCode\CustomFields\Models\CustomField;

final class CustomFieldCreated implements ShouldDispatchAfterCommit
{
    public function __construct(public readonly CustomField $field) {}
}
