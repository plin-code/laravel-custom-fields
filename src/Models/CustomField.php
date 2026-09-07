<?php

declare(strict_types=1);

namespace PlinCode\CustomFields\Models;

use Illuminate\Database\Eloquent\Model;

class CustomField extends Model
{
    protected $guarded = [];

    public function getTable(): string
    {
        return (string) config('laravel-custom-fields.tables.fields');
    }

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
