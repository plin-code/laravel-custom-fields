<?php

declare(strict_types=1);

namespace PlinCode\CustomFields\Models;

use Illuminate\Database\Eloquent\Model;

class CustomFieldValue extends Model
{
    protected $guarded = [];

    public function getTable(): string
    {
        return (string) config('laravel-custom-fields.tables.values');
    }

    protected function casts(): array
    {
        return [
            'value_json' => 'array',
            'value_boolean' => 'boolean',
            'value_integer' => 'integer',
        ];
    }
}
