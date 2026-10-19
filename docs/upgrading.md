# Upgrading

This page is for an application on 0.1.x or 0.2.x moving to 0.6.0. Releases 0.3.0 to 0.6.0
changed no method signature, but they changed what several calls accept, store and throw.
Read each section, then check the data notes at the end. The full history is in the
[changelog](https://github.com/plin-code/laravel-custom-fields/blob/main/CHANGELOG.md).

## Dates and datetimes (0.3.0)

`date` values are stored and read as `Y-m-d`, `datetime` values as `Y-m-d H:i:s`. Any input
the `date` rule accepts is normalised on write: `15 January 2024`, `01/15/2024` (read as
month/day/year), ISO 8601 strings and Carbon instances. Before, the submitted string was
written as it was.

A `datetime` is interpreted in the application timezone. A naive string is read in that
timezone, and a value with an offset or `Z` is converted to it on write, on read and when
it is used as a filter value. Before, a naive string was read in PHP's default timezone. A
`date` keeps the calendar date it was written with and is never shifted.

The `whereCustomField()` scope passes its value through the same normalisation, so
`whereCustomField('date-of-birth', '2 March 1985')` now matches a record stored as
`1985-03-02`.

## Decimals (0.3.0)

`decimal` values are validated against the column: at most six fraction digits, within
`±99999999999999.999999`, no exponent notation and no surrounding whitespace. A leading `+`
is stripped. Before, an out of range value failed inside the database with a 500, and extra
fraction digits were rounded silently. A decimal stored in exponent form before 0.3.0 now
reads back as stored, instead of having its trailing zeros trimmed into a different value.

## Empty strings (0.3.0)

An empty string passed to `setCustomField()` or `setCustomFields()` means `null`: the stored
row is removed and `CustomFieldValueDeleted` fires. Under complete validation a required
field submitted as `''` is refused. Before, a `number` stored `0`, a `boolean` stored
`false`, and a `date` or `decimal` failed inside the database. A string made only of
whitespace is still stored.

If your forms post empty inputs for fields the user did not touch, a partial write now
clears those fields. Drop untouched keys from the payload if that is not what you want.

## Slugs and types (0.4.0)

An explicit `slug` passed to `CustomField::create()` must match
`/^[a-z0-9][a-z0-9_-]{0,99}$/`, otherwise an `InvalidArgumentException` is thrown. Generated
slugs are unaffected. Before, a slug containing `.` or `*` bypassed the type rules, and a
slug containing `:` could collide with the filter name of another field.

The `type` of a definition is resolved through the registry on create, and on update when
it changes, so a typo such as `multi_select` throws an `InvalidArgumentException` at save
time. Before, the row was saved and every later read, write, filter and sort on that entity
threw. Register your custom types before you save a definition that uses them.

## Entity registration (0.5.0)

`CustomFields::registerEntity()` throws an `InvalidArgumentException` when the key is
already mapped to another class in `Relation::morphMap()`, and when the model is already
registered under a different key. Registering the same model under the same key is still a
no op. Before, an alias owned by another model was overwritten silently, and a second key
for the same model was ignored. If your application registers a model twice with two keys,
or reuses a morph alias, it now fails at boot.

## Deleting a host model (0.5.0)

Deleting a model that uses the trait deletes its stored values one by one through Eloquent,
so `CustomFieldValueDeleted` fires for each. A soft deleting model keeps them until it is
force deleted. Before, the rows survived the delete, and a reused key inherited stale
values. A bulk delete through the query builder still leaves rows behind.

Listeners on `CustomFieldValueDeleted` now run when a host model is deleted. Check that
they do not reload the host, which no longer exists by then.

## Filter values (0.6.0)

Values for `equals`, `in`, `greater_than`, `less_than` and `between` are validated against
the rules of the field type before they reach the query. A failing value, or an empty
element inside a list such as `1,,2`, throws Spatie's `InvalidFilterValue`. Before,
`?filter[cf_age]=abc` matched the records whose age is `0`, and a malformed date or decimal
failed inside PostgreSQL with a 500. A filter with no value at all, `?filter[cf_age]=`, is
turned into `null` by Laravel's `ConvertEmptyStringsToNull` middleware and ignored by
Spatie; only an application without that middleware gets `InvalidFilterValue` for it. A
`select` filter with a key outside the options, a
`boolean` filter such as `maybe` and a `number` filter such as `1.5` are refused too,
instead of returning an empty result or a silently cast one.

`InvalidFilterValue` is not an HTTP exception, so it renders as a 500 unless you map it to
a 400, see [invalid filter values](querying.md#invalid-filter-values).

## Typed column indexes (0.7.0)

The shipped migration now indexes `value_integer`, `value_decimal`, `value_date` and
`value_datetime`, each together with `custom_field_id`, next to the existing indexes on
`value_string` and `value_boolean`. Filters and sorts on number, decimal, date and datetime
fields run as a subquery on the field and the typed column, and no longer scan every value
of the field.

An application that ran the migration before 0.7.0 does not get them, because a migration
that already ran is not run again. Create a migration of your own with
`php artisan make:migration add_typed_value_indexes` and paste this in. It reads the same
table name the package reads, so it follows a changed `tables.values` configuration.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('laravel-custom-fields.tables.values'), function (Blueprint $table): void {
            $table->index(['custom_field_id', 'value_integer'], 'cfv_field_integer_index');
            $table->index(['custom_field_id', 'value_decimal'], 'cfv_field_decimal_index');
            $table->index(['custom_field_id', 'value_date'], 'cfv_field_date_index');
            $table->index(['custom_field_id', 'value_datetime'], 'cfv_field_datetime_index');
        });
    }
};
```

A new install needs nothing. If you already added an index on one of these columns
yourself, drop it first or remove its line from the migration above.

## Data notes

- Rows written before 0.3.0 on MySQL and PostgreSQL were already stored canonically and are
  unaffected.
- Rows written before 0.3.0 on SQLite may hold a non canonical date string. They read back
  in the canonical form, but equality, range filters and sorting compare the stored text,
  so save those values again to normalise them.
- On SQLite a `decimal` is stored with floating point affinity: values beyond about fifteen
  significant digits lose precision and very small values read back in exponent form. Use
  MySQL or PostgreSQL when exact decimals matter.
- Values left behind by host models deleted before 0.5.0 are still in the values table.
  Find them by joining `valuable_type` and `valuable_id` against your tables and delete them.
