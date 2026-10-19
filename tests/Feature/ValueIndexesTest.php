<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

/** @return array<string, array<int, string>> */
function valueIndexColumns(): array
{
    $indexes = [];

    foreach (Schema::getIndexes(config('laravel-custom-fields.tables.values')) as $index) {
        $indexes[$index['name']] = $index['columns'];
    }

    return $indexes;
}

it('indexes every filterable typed column together with the field', function (string $name, string $column): void {
    expect(valueIndexColumns())->toHaveKey($name, ['custom_field_id', $column]);
})->with([
    ['cfv_field_string_index', 'value_string'],
    ['cfv_field_boolean_index', 'value_boolean'],
    ['cfv_field_integer_index', 'value_integer'],
    ['cfv_field_decimal_index', 'value_decimal'],
    ['cfv_field_date_index', 'value_date'],
    ['cfv_field_datetime_index', 'value_datetime'],
]);

it('leaves the text and json columns unindexed', function (): void {
    $columns = array_merge(...array_values(valueIndexColumns()));

    expect($columns)->not->toContain('value_text')->not->toContain('value_json');
});
