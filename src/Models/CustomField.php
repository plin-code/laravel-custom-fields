<?php

declare(strict_types=1);

namespace PlinCode\CustomFields\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use PlinCode\CustomFields\Contracts\FieldType;
use PlinCode\CustomFields\Facades\CustomFields;
use PlinCode\CustomFields\Observers\CustomFieldObserver;

class CustomField extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::observe(CustomFieldObserver::class);
    }

    /** @return HasMany<CustomFieldValue, $this> */
    public function values(): HasMany
    {
        /** @var class-string<CustomFieldValue> $model */
        $model = CustomFields::valueModel();

        return $this->hasMany($model, 'custom_field_id');
    }

    public function fieldType(): FieldType
    {
        return CustomFields::type($this->getAttribute('type'));
    }

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
