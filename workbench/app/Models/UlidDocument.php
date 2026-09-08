<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use PlinCode\CustomFields\Concerns\HasCustomFields;

/** A host whose primary key is a ulid, for the "morph_key_type" => "ulid" schema. */
class UlidDocument extends Model
{
    use HasCustomFields;
    use HasUlids;

    protected $guarded = [];
}
