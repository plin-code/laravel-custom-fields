<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use PlinCode\CustomFields\CustomFieldsServiceProvider;

function typedIndexMigration(): Migration
{
    $migration = require dirname(__DIR__, 2).'/database/migrations/2026_01_01_000002_add_typed_indexes_to_custom_field_values_table.php';

    expect($migration)->toBeInstanceOf(Migration::class);

    return $migration;
}

/** @return array<int, string> */
function valueIndexNames(): array
{
    $names = array_map(
        static fn (array $index): string => strtolower($index['name']),
        Schema::getIndexes(config('laravel-custom-fields.tables.values')),
    );

    sort($names);

    return $names;
}

/** @return array<int, string> */
function typedIndexNames(): array
{
    return [
        'cfv_field_date_index',
        'cfv_field_datetime_index',
        'cfv_field_decimal_index',
        'cfv_field_integer_index',
    ];
}

it('publishes the index migration under the migrations tag', function (): void {
    $published = array_map(
        'basename',
        array_keys(ServiceProvider::pathsToPublish(CustomFieldsServiceProvider::class, 'laravel-custom-fields-migrations')),
    );

    expect($published)->toHaveCount(3)
        ->and($published[2])->toBe('2026_01_01_000002_add_typed_indexes_to_custom_field_values_table.php');
});

it('leaves the six indexes of a fresh install untouched', function (): void {
    typedIndexMigration()->up();

    expect(array_values(array_filter(
        valueIndexNames(),
        static fn (string $name): bool => str_starts_with($name, 'cfv_field_') && ! str_ends_with($name, '_unique'),
    )))->toBe([
        'cfv_field_boolean_index',
        'cfv_field_date_index',
        'cfv_field_datetime_index',
        'cfv_field_decimal_index',
        'cfv_field_integer_index',
        'cfv_field_string_index',
    ]);
});

it('recreates the typed indexes an older install lacks', function (): void {
    Schema::table(config('laravel-custom-fields.tables.values'), function ($table): void {
        foreach (typedIndexNames() as $name) {
            $table->dropIndex($name);
        }
    });

    expect(valueIndexNames())->not->toContain(...typedIndexNames());

    typedIndexMigration()->up();

    expect(valueIndexNames())->toContain(...typedIndexNames());
});

it('can run twice without failing', function (): void {
    typedIndexMigration()->up();
    typedIndexMigration()->up();

    expect(valueIndexNames())->toContain(...typedIndexNames());
});
