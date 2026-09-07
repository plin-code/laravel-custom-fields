<?php

declare(strict_types=1);

namespace PlinCode\CustomFields\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PlinCode\CustomFields\Contracts\FieldType;
use PlinCode\CustomFields\Database\Factories\CustomFieldFactory;
use PlinCode\CustomFields\Events\CustomFieldCreated;
use PlinCode\CustomFields\Events\CustomFieldDeleted;
use PlinCode\CustomFields\Events\CustomFieldUpdated;
use PlinCode\CustomFields\Facades\CustomFields;
use PlinCode\CustomFields\Observers\CustomFieldObserver;

class CustomField extends Model
{
    protected $dispatchesEvents = [
        'created' => CustomFieldCreated::class,
        'updated' => CustomFieldUpdated::class,
        'deleted' => CustomFieldDeleted::class,
    ];

    protected $guarded = [];

    /** @return Factory<self> */
    protected static function newFactory(): Factory
    {
        return CustomFieldFactory::new();
    }

    protected static function booted(): void
    {
        static::observe(CustomFieldObserver::class);

        static::creating(function (self $field): void {
            if ($field->getIncrementing()) {
                return;
            }

            $field->setAttribute($field->getKeyName(), config('laravel-custom-fields.key_type') === 'ulid'
                ? (string) Str::ulid()
                : (string) Str::uuid());
        });
    }

    public function getKeyType(): string
    {
        return config('laravel-custom-fields.key_type') === 'id' ? 'int' : 'string';
    }

    public function getIncrementing(): bool
    {
        return config('laravel-custom-fields.key_type') === 'id';
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

    /** @return array<int, array{key: string, label: string, is_active: bool}> */
    public function optionsForInput(): array
    {
        return array_values(array_filter(
            (array) $this->getAttribute('options'),
            static fn (array $option): bool => (bool) ($option['is_active'] ?? true),
        ));
    }

    /**
     * Every option key defined on the field, including the inactive ones.
     *
     * @return array<int, string>
     */
    public function optionKeys(): array
    {
        return array_values(array_map(
            static fn (array $option): string => (string) ($option['key'] ?? ''),
            (array) $this->getAttribute('options'),
        ));
    }

    /**
     * The option keys that can still be assigned to a value.
     *
     * @return array<int, string>
     */
    public function activeOptionKeys(): array
    {
        return array_values(array_map(
            static fn (array $option): string => (string) $option['key'],
            $this->optionsForInput(),
        ));
    }

    /** @param array<int, array{key: string, label: string, is_active?: bool}> $options */
    public function updateOptions(array $options): self
    {
        $existing = collect((array) $this->getAttribute('options'))->pluck('key')->all();
        $next = collect($options)->pluck('key')->all();

        foreach ($existing as $key) {
            if (! in_array($key, $next, true)) {
                throw new InvalidArgumentException("Custom field option [{$key}] cannot be removed.");
            }
        }

        $this->setAttribute('options', $options);
        $this->save();

        return $this;
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
