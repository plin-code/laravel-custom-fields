<?php

declare(strict_types=1);

namespace PlinCode\CustomFields\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use PlinCode\CustomFields\Models\CustomField;

/** @extends Factory<CustomField> */
class CustomFieldFactory extends Factory
{
    protected $model = CustomField::class;

    public function definition(): array
    {
        return [
            'entity_type' => 'article',
            'name' => fake()->unique()->words(2, true),
            'type' => 'text',
            'options' => null,
            'is_required' => false,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
