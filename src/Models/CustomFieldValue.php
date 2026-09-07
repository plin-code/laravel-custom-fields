<?php

declare(strict_types=1);

namespace PlinCode\CustomFields\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use PlinCode\CustomFields\Events\CustomFieldValueSaved;
use PlinCode\CustomFields\Facades\CustomFields;

class CustomFieldValue extends Model
{
    protected $dispatchesEvents = [
        'saved' => CustomFieldValueSaved::class,
    ];

    protected $guarded = [];

    /** @return BelongsTo<CustomField, $this> */
    public function customField(): BelongsTo
    {
        /** @var class-string<CustomField> $model */
        $model = CustomFields::fieldModel();

        return $this->belongsTo($model, 'custom_field_id');
    }

    /** @return MorphTo<Model, $this> */
    public function valuable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getValue(): mixed
    {
        $field = $this->customField;
        $value = $this->getAttribute($field->fieldType()->storageColumn());

        return $value === null ? $field->fieldType()->default() : $field->fieldType()->deserialize($value, $field);
    }

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
