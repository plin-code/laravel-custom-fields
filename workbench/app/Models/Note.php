<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use PlinCode\CustomFields\Concerns\HasCustomFields;

/**
 * A host model that soft deletes, so the values of a trashed host can be told
 * apart from the values of a host that is gone for good.
 */
class Note extends Model
{
    use HasCustomFields;
    use SoftDeletes;

    protected $guarded = [];
}
