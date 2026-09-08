<?php

declare(strict_types=1);

namespace PlinCode\CustomFields\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use PlinCode\CustomFields\Models\CustomField;

final readonly class CustomFieldCreated implements ShouldDispatchAfterCommit
{
    public function __construct(public CustomField $field) {}
}
