<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use PlinCode\CustomFields\Concerns\HasCustomFields;

class Article extends Model
{
    use HasCustomFields;

    protected $guarded = [];
}
