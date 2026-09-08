<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use PlinCode\CustomFields\Concerns\HasCustomFields;

/**
 * A second host model, so isolation between two entity types is observable
 * instead of being asserted on a single model registered twice.
 */
class Project extends Model
{
    use HasCustomFields;

    protected $guarded = [];
}
