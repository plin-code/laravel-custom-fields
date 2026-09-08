<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use PlinCode\CustomFields\Concerns\HasCustomFields;

/** A host whose primary key is a uuid, for the "morph_key_type" => "uuid" schema. */
class UuidDocument extends Model
{
    use HasCustomFields;
    use HasUuids;

    protected $guarded = [];
}
