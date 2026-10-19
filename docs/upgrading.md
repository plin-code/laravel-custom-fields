# Upgrading

This page is for an application on 0.1.x or 0.2.x moving to 1.0.0. Releases 0.3.0 to 1.0.0
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
element inside a list such as `1,,2`, throws `InvalidFilterValueException`. Before,
`?filter[cf_age]=abc` matched the records whose age is `0`, and a malformed date or decimal
failed inside PostgreSQL with a 500. A filter with no value at all, `?filter[cf_age]=`, is
turned into `null` by Laravel's `ConvertEmptyStringsToNull` middleware and ignored by
Spatie; only an application without that middleware gets `InvalidFilterValueException` for it. A
`select` filter with a key outside the options, a
`boolean` filter such as `maybe` and a `number` filter such as `1.5` are refused too,
instead of returning an empty result or a silently cast one.

`InvalidFilterValueException` extends Spatie's `InvalidFilterValue` and is an HTTP exception
with status 400, so Laravel renders it as a 400 without a mapping, see
[invalid filter values](querying.md#invalid-filter-values).

A bare `contains=` is skipped under the default middleware, and an empty item inside a list
is refused. An existing mapping of Spatie's `InvalidFilterValue` to a 400 in your exception handler
can be removed.

## Typed column indexes (1.0.0)

The shipped migration now indexes `value_integer`, `value_decimal`, `value_date` and
`value_datetime`, each together with `custom_field_id`, next to the existing indexes on
`value_string` and `value_boolean`. Filters and sorts on number, decimal, date and datetime
fields run as a subquery on the field and the typed column, and no longer scan every value
of the field.

An application that ran the migration before 1.0.0 does not get them, because a migration
that already ran is not run again. A third migration,
`add_typed_indexes_to_custom_field_values_table`, adds them. Publish it and run it:

```bash
php artisan vendor:publish --tag="laravel-custom-fields-migrations"
php artisan migrate
```

Publishing copies only the file your application does not have yet, so the two migrations
you already published are left alone. The migration reads the values table name from the
`tables.values` configuration and creates each index only when it is missing. An index you
added by hand under the same name is skipped, and an index you added under a different name
is left in place, so the column then carries both. A new install needs nothing, the first
migration already creates all six indexes and the third one does nothing.

## Mass assignment (1.0.0)

Both models use `$fillable`. Any key outside it, including `id`, `created_at`, `updated_at`
and the extra columns of a subclass, is dropped on `create()` and `fill()`, or throws
`MassAssignmentException` under `Model::shouldBeStrict()` or
`preventSilentlyDiscardingAttributes()`. A subclass configured under `models.*` must merge
its columns into `$fillable`. An import that sets timestamps must use `forceFill()`.

## Contains on PostgreSQL (1.0.0)

`contains` uses `ILIKE` on PostgreSQL and is now case insensitive there. MySQL and SQLite
behave as before. The escape character changed from `!` to a backslash, so MySQL with
`NO_BACKSLASH_ESCAPES` in `sql_mode` rejects the escape clause.

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
