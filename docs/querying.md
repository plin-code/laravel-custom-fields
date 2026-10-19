# Querying

Custom fields can be queried two ways: the `whereCustomField()` scope for your own code, and
request filters and sorts built for `spatie/laravel-query-builder` for an HTTP listing. This
page covers both, the names a client sends, the ten filter operations, how filter values
are parsed and validated, and what the queries cost. The examples reuse the definitions
from [defining fields](defining-fields.md#creating-a-definition), and the README has a
[worked example](https://github.com/plin-code/laravel-custom-fields#a-worked-example) with
three patients.

## The whereCustomField scope

The trait adds a scope for a direct lookup on one field:

```php
Patient::whereCustomField('risk-level', 'high')->get();
Patient::whereCustomField('date-of-birth', '2 March 1985')->get(); // compared as 1985-03-02
Patient::whereCustomField('allergies', 'latex')->get();           // JSON containment
```

It resolves an active definition, throws `UnknownCustomFieldException` when the slug is
unknown or inactive, and constrains the typed storage column of that field through a
`whereHas` on the value relation.

On every type except `multiselect`, the value goes through the `serialize()` method of the
field type first, the same step a write takes, so the scope compares what a write would
store: a date in any form the `date` rule accepts becomes `Y-m-d`, a `datetime` is converted
to the application timezone, a decimal loses a leading `+`, a number is cast to an integer
and `'true'` or `1` become a boolean. On a `multiselect` the value is passed as it is to
`whereJsonContains`, a JSON containment check instead of equality, so a single key matches
every record that holds it, and an array of keys matches the records that hold all of them.

The scope does not validate the value. A date string that cannot be parsed throws Carbon's
`InvalidFormatException`, and an empty string given for a `date` or a `datetime` is parsed
as the current time, so skip the scope when there is nothing to compare. Anything richer
than a single comparison belongs to the request filters below.

## Filters and sorts for a request

`CustomFields::filtersFor()` returns one `AllowedFilter` per filter operation that the type
of every active field declares, and `CustomFields::sortsFor()` returns an `AllowedSort` for
every active field whose type declares `sort`. Both come back ordered by `sort_order` and
then by slug. Spatie expects them spread:

```php
use App\Models\Patient;
use PlinCode\CustomFields\Facades\CustomFields;
use Spatie\QueryBuilder\QueryBuilder;

$patients = QueryBuilder::for(Patient::class)
    ->allowedFilters(...CustomFields::filtersFor(Patient::class))
    ->allowedSorts(...CustomFields::sortsFor(Patient::class))
    ->paginate();
```

Each of those calls reads the definitions from the database. When you need both, ask for
them together and pay for one query instead of two:

```php
$options = CustomFields::queryOptionsFor(Patient::class);

$patients = QueryBuilder::for(Patient::class)
    ->allowedFilters(...$options['filters'])
    ->allowedSorts(...$options['sorts'])
    ->paginate();
```

Call them while serving a request, never from the `register()` or `boot()` method of a
service provider.

## The names a client sends

A filter is named after the `key_prefix` and the slug. Equality keeps the bare name, and
every other operation is suffixed with a colon, a character a slug never contains. Sorts
always use the bare name.

With the definitions above and the default `cf_` prefix, a client can send the following.
The line breaks are here for readability, a real request sends one line:

```http
GET /api/patients
    ?filter[cf_risk-level]=high
    &filter[cf_risk-level:in]=high,low
    &filter[cf_date-of-birth:between]=1980-01-01,1989-12-31
    &filter[cf_allergies:contains_any]=pollen,latex
    &filter[cf_notes:contains]=follow%20up
    &filter[cf_notes:is_null]=0
    &sort=-cf_date-of-birth
```

You can build the same names in your own code, for a link or for an OpenAPI document:

```php
CustomFields::keyPrefix();                       // 'cf_'
CustomFields::filterName('risk-level');          // 'cf_risk-level'
CustomFields::filterName('risk-level', 'in');    // 'cf_risk-level:in'
CustomFields::sortName('date-of-birth');         // 'cf_date-of-birth'
```

## The operations

There are ten filter operations. Sorting is not one of them: a type opts into sorting by
listing `sort` in its `queryOperations()`.

| Operation | Value the client sends | Meaning |
| --- | --- | --- |
| `equals` | a single value, or a comma separated list | Exact match. A list behaves as an `in`. |
| `in` | a comma separated list | The value is one of the given ones. |
| `contains` | a value, or a comma separated list | A `LIKE %value%` match through `plin-code/laravel-sql-dialect`. It ignores letter case on PostgreSQL (`ILIKE`), follows the collation on MySQL and MariaDB, and ignores case for ASCII letters only on SQLite. `%`, `_` and a backslash in the term are matched literally. A list matches any of the values. |
| `greater_than`, `less_than` | a single value | A strict comparison. A list is rejected. |
| `between` | exactly two comma separated values | An inclusive range. Any other count is rejected. |
| `is_null` | `1` to ask for absent, `0` to ask for present | A record with no value row and a record whose typed column is null both count as absent. |
| `is_not_null` | `1` to ask for present, `0` to ask for absent | The complement. A `multiselect` stored as an empty array counts as present. |
| `contains_any` | a comma separated list | The stored JSON array holds at least one of the values. |
| `contains_all` | a comma separated list | The stored JSON array holds all of the values. |

A field only exposes the operations its type declares, so nothing arbitrary can be built
from a request; the [field types table](defining-fields.md#field-types) lists them. A
`multiselect` never exposes a bare equality filter, so `filter[cf_allergies]=latex` raises
Spatie's `InvalidFilterQuery`, a 400, like any other filter name that was not allowed.

## How Spatie parses a filter value

Spatie reads the value before the package sees it, and two of its rules matter:

- A value containing a comma is split into a list. That is what makes `in` and `between`
  work, and it also means a literal comma cannot be searched for: `filter[cf_notes]=a,b`
  looks for `a` or `b`, never for `a,b`.
- The strings `true` and `false` become booleans. A `boolean` field is served well by
  this, but a text field cannot be compared with the word `true` or `false`: equality
  refuses the boolean as an invalid value, and `contains` searches for `1` for `true` and
  refuses `false` as an empty value.

## Invalid filter values

Values for `equals`, `in`, `greater_than`, `less_than` and `between` are validated against
the `rules()` of the field type, then normalised through its `serialize()`, exactly like a
write. A value that fails, an empty element inside a list, a list given to `greater_than`
or `less_than`, and a `between` with anything other than two values all throw
`PlinCode\CustomFields\Exceptions\InvalidFilterValueException`. So `filter[cf_age]=abc` on a `number`,
`filter[cf_date-of-birth]=2024-13-45`, `filter[cf_risk-level]=closed` on a `select` without
that key, `filter[cf_age]=1,,2` and `filter[cf_date-of-birth:between]=,2024-01-31` are all
refused instead of matching the wrong rows. An empty `contains` value, such as
`filter[cf_notes:contains]=a,,b`, is refused the same way, because it would otherwise match
every row that has a value. The presence filters, `contains_any` and `contains_all` are not
validated this way.

A filter sent with no value at all, such as `filter[cf_age]=`, is different. Laravel's
`ConvertEmptyStringsToNull` middleware turns the empty query string value into `null`, and
Spatie skips a filter whose value is `null`, so the filter is ignored silently. It is
refused with `InvalidFilterValueException` only when your application removes that middleware.

A `null` element inside a list is refused too. Under the default middleware
`filter[cf_age][]=` reaches the filter as `[null]`, and so does the empty bound of
`filter[cf_age:between][]=1&filter[cf_age:between][]=`. Only a bare `null`, the whole
filter value, is skipped.

`InvalidFilterValueException` extends Spatie's `InvalidFilterValue`, so an existing
`catch (InvalidFilterValue)` keeps working, and it implements Symfony's
`HttpExceptionInterface` with status 400, like Spatie's `InvalidFilterQuery`. Laravel
renders it as a 400 response out of the box, with no mapping in `bootstrap/app.php`.

A date or datetime filter value goes through the same normalisation as a stored value, so
`filter[cf_date-of-birth]=03/02/1985` matches a record stored as `1985-03-02`, and a
`datetime` value with an offset is converted to the application timezone before it is
compared.

## Sorting

Records without a value for the sorted field come last in both directions, on MySQL,
PostgreSQL and SQLite alike. The sort is expressed as a correlated subquery, so your own
select, aggregates and existing order are left untouched and no row is duplicated. The
direction is validated through
`PlinCode\EloquentSorts\Support\Direction::normalise()`.

## Composing with other sorts

Custom field sorts are ordinary `AllowedSort` instances, so they sit next to your own
columns and next to the sorts from `plin-code/laravel-eloquent-sorts`:

```php
use App\Models\Patient;
use PlinCode\CustomFields\Facades\CustomFields;
use PlinCode\EloquentSorts\Sorts\RelationSorter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

$options = CustomFields::queryOptionsFor(Patient::class);

$patients = QueryBuilder::for(Patient::class)
    ->allowedFilters(...$options['filters'])
    ->allowedSorts(
        AllowedSort::field('last_name'),
        AllowedSort::custom('clinic', new RelationSorter('clinics', 'clinic_id')),
        ...$options['sorts'],
    )
    ->paginate();
```

A client then sends `?sort=clinic,-cf_date-of-birth` and gets the patients ordered by
clinic name and then by date of birth, newest first.

## Cost and indexes

Every value filter is a `whereHas` subquery on the values table, and every sort adds two
correlated subqueries. The values table is indexed on the field together with each of
`value_string`, `value_boolean`, `value_integer`, `value_decimal`, `value_date` and
`value_datetime`, so text, select, boolean, number, decimal, date and datetime fields are
all served. `value_text` and `value_json` are not indexed. `contains` is
a `LIKE` with a leading wildcard, which no ordinary index can serve, whatever the column.
