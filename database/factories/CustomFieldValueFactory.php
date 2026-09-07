<?php

declare(strict_types=1);

namespace PlinCode\CustomFields\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use PlinCode\CustomFields\Models\CustomFieldValue;

/** @extends Factory<CustomFieldValue> */
class CustomFieldValueFactory extends Factory
{
    protected $model = CustomFieldValue::class;

    public function definition(): array
    {
        return [
            'custom_field_id' => null,
            'valuable_type' => 'article',
            'valuable_id' => 1,
            'value_string' => fake()->word(),
        ];
    }
}
