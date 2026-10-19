<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\Model;
use PlinCode\CustomFields\Database\Factories\CustomFieldFactory;
use PlinCode\CustomFields\Database\Factories\CustomFieldValueFactory;
use PlinCode\CustomFields\Models\CustomField;
use PlinCode\CustomFields\Models\CustomFieldValue;

afterEach(function (): void {
    Model::preventSilentlyDiscardingAttributes(false);
});

it('ignores a client supplied id on a definition', function (): void {
    $field = CustomField::create(['id' => 999, 'entity_type' => 'article', 'name' => 'Rank', 'type' => 'number']);

    expect($field->getKey())->toBeInt()->not->toBe(999)
        ->and(CustomField::query()->count())->toBe(1)
        ->and(CustomField::query()->value('id'))->toBe($field->getKey());
});

it('ignores a client supplied id on a value', function (): void {
    $field = CustomField::create(['entity_type' => 'article', 'name' => 'Rank', 'type' => 'number']);

    $value = CustomFieldValue::create([
        'id' => 'x',
        'custom_field_id' => $field->getKey(),
        'valuable_type' => 'article',
        'valuable_id' => 1,
        'value_integer' => 3,
    ]);

    expect($value->getKey())->toBeInt()->not->toBe('x')
        ->and(CustomFieldValue::query()->count())->toBe(1)
        ->and(CustomFieldValue::query()->value('id'))->toBe($value->getKey());
});

it('ignores unknown keys on a definition', function (): void {
    $field = CustomField::create([
        'entity_type' => 'article',
        'name' => 'Rank',
        'type' => 'number',
        'created_at' => '2001-01-01 00:00:00',
        'owner_id' => 5,
    ]);

    expect($field->getAttributes())->not->toHaveKey('owner_id')
        ->and($field->created_at->year)->not->toBe(2001);
});

it('throws on an unknown key when discarding attributes is prevented', function (): void {
    Model::preventSilentlyDiscardingAttributes();

    expect(fn () => CustomField::create(['entity_type' => 'article', 'name' => 'Rank', 'type' => 'number', 'id' => 999]))
        ->toThrow(MassAssignmentException::class)
        ->and(fn () => CustomFieldValue::create(['valuable_id' => 1, 'id' => 'x']))
        ->toThrow(MassAssignmentException::class);
});

it('round trips every fillable attribute of a definition', function (): void {
    $attributes = [
        'entity_type' => 'article',
        'name' => 'priority',
        'slug' => 'priority-level',
        'type' => 'select',
        'options' => [['key' => 'low', 'label' => 'Low'], ['key' => 'high', 'label' => 'High']],
        'is_required' => true,
        'is_active' => false,
        'sort_order' => 4,
    ];

    $field = CustomField::create($attributes)->fresh();

    expect(array_keys($attributes))->toEqualCanonicalizing($field->getFillable())
        ->and($field->only(array_keys($attributes)))->toBe($attributes);
});

it('round trips every fillable attribute of a value', function (): void {
    $field = CustomField::create(['entity_type' => 'article', 'name' => 'Rank', 'type' => 'number']);
    $attributes = [
        'custom_field_id' => $field->getKey(),
        'valuable_type' => 'article',
        'valuable_id' => 7,
        'value_string' => 'short',
        'value_text' => 'a longer text',
        'value_integer' => 42,
        'value_decimal' => '12.500000',
        'value_boolean' => true,
        'value_date' => '2026-03-04',
        'value_datetime' => '2026-03-04 10:11:12',
        'value_json' => ['a' => 1],
    ];

    $value = CustomFieldValue::create($attributes)->fresh();

    expect(array_keys($attributes))->toEqualCanonicalizing($value->getFillable())
        ->and($value->getAttribute('custom_field_id'))->toBe($field->getKey())
        ->and($value->valuable_type)->toBe('article')
        ->and((int) $value->valuable_id)->toBe(7)
        ->and($value->value_string)->toBe('short')
        ->and($value->value_text)->toBe('a longer text')
        ->and($value->value_integer)->toBe(42)
        ->and((float) $value->value_decimal)->toBe(12.5)
        ->and($value->value_boolean)->toBeTrue()
        ->and(substr((string) $value->getRawOriginal('value_date'), 0, 10))->toBe('2026-03-04')
        ->and(substr((string) $value->getRawOriginal('value_datetime'), 0, 19))->toBe('2026-03-04 10:11:12')
        ->and($value->value_json)->toBe(['a' => 1]);
});

it('still creates rows from the factories', function (): void {
    $field = CustomFieldFactory::new()->create();
    $value = CustomFieldValueFactory::new()->create(['custom_field_id' => $field->getKey()]);

    expect(CustomField::query()->count())->toBe(1)
        ->and($value->exists)->toBeTrue()
        ->and($value->value_string)->not->toBeNull();
});
