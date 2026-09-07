<?php

declare(strict_types=1);

namespace PlinCode\CustomFields;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PlinCode\CustomFields\Contracts\FieldType;

class CustomFields
{
    /** @var array<string, class-string<FieldType>> */
    private array $types = [];

    /** @var array<string, array{key: string, label: string}> */
    private array $entities = [];

    public function registerType(string $type): void
    {
        $key = $type::key();

        if (isset($this->types[$key])) {
            throw new InvalidArgumentException("Custom field type [{$key}] is already registered.");
        }

        /** @var class-string<FieldType> $type */
        $this->types[$key] = $type;
    }

    public function type(string $key): FieldType
    {
        if (! isset($this->types[$key])) {
            throw new InvalidArgumentException("Unknown custom field type [{$key}].");
        }

        return app($this->types[$key]);
    }

    /** @return array<string, FieldType> */
    public function types(): array
    {
        return array_map(fn (string $type): FieldType => app($type), $this->types);
    }

    public function registerEntity(string $model, string $key, ?string $label = null): void
    {
        if (in_array($key, array_column($this->entities, 'key'), true)) {
            throw new InvalidArgumentException("Custom field entity key [{$key}] is already registered.");
        }

        $this->entities[$model] = [
            'key' => $key,
            'label' => $label ?? Str::headline(class_basename($model)),
        ];
    }

    /** @return array<string, array{key: string, label: string}> */
    public function entities(): array
    {
        return $this->entities;
    }

    public function entityKey(Model|string $model): string
    {
        $class = $model instanceof Model ? $model::class : $model;

        if (! isset($this->entities[$class])) {
            throw new InvalidArgumentException("Model [{$class}] is not registered for custom fields.");
        }

        return $this->entities[$class]['key'];
    }

    public function fieldModel(): string
    {
        return config('laravel-custom-fields.models.custom_field');
    }

    public function valueModel(): string
    {
        return config('laravel-custom-fields.models.custom_field_value');
    }
}
