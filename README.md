<p align="center">
  <img src="https://raw.githubusercontent.com/plin-code/laravel-custom-fields/main/art/banner.png" alt="Laravel Custom Fields">
</p>

<div align="center">
    <h1>Laravel Custom Fields</h1>
</div>

<p align="center">
    <a href="https://packagist.org/packages/plin-code/laravel-custom-fields"><img src="https://img.shields.io/packagist/v/plin-code/laravel-custom-fields.svg?style=flat-square" alt="Packagist"></a>
    <a href="https://packagist.org/packages/plin-code/laravel-custom-fields"><img src="https://img.shields.io/packagist/php-v/plin-code/laravel-custom-fields.svg?style=flat-square" alt="PHP from Packagist"></a>
    <a href="https://packagist.org/packages/plin-code/laravel-custom-fields"><img src="https://badge.laravel.cloud/badge/plin-code/laravel-custom-fields?style=flat" alt="Laravel versions"></a>
    <a href="https://github.com/plin-code/laravel-custom-fields/actions"><img alt="GitHub Workflow Status (main)" src="https://img.shields.io/github/actions/workflow/status/plin-code/laravel-custom-fields/tests.yml?branch=main&label=Tests&style=flat-square"></a>
    <a href="https://packagist.org/packages/plin-code/laravel-custom-fields"><img src="https://img.shields.io/packagist/dt/plin-code/laravel-custom-fields.svg?style=flat-square" alt="Total Downloads"></a>
</p>

Let the users of your product add typed fields to an Eloquent model at runtime, such as a
patient's risk level or a project's budget, without writing a migration for each one. Every
value lands in a typed column, is validated by the type of its field, and can be filtered
and sorted from a request through `spatie/laravel-query-builder`, as in
`?filter[cf_risk-level]=high`.

The package is headless. It ships no UI, no routes and no authorization; your application
owns those.

## Quick start

If your models use UUID or ULID keys, publish the config first and set `morph_key_type`
before you migrate, see
[installation](https://github.com/plin-code/laravel-custom-fields/blob/main/docs/installation.md#migrations).

```bash
composer require plin-code/laravel-custom-fields
php artisan vendor:publish --tag="laravel-custom-fields-migrations"
php artisan migrate
```

```php
use Illuminate\Database\Eloquent\Model;
use PlinCode\CustomFields\Concerns\HasCustomFields;
use PlinCode\CustomFields\Facades\CustomFields;
use PlinCode\CustomFields\Models\CustomField;
use Spatie\QueryBuilder\QueryBuilder;

// app/Models/Patient.php
class Patient extends Model
{
    use HasCustomFields;
}

// In the boot() method of a service provider, on every request.
CustomFields::registerEntity(Patient::class, 'patient');

// Wherever your product lets an administrator define a field.
CustomField::create([
    'entity_type' => CustomFields::entityKey(Patient::class),
    'name' => 'Risk level',
    'type' => 'select',
    'options' => [
        ['key' => 'low', 'label' => 'Low', 'is_active' => true],
        ['key' => 'high', 'label' => 'High', 'is_active' => true],
    ],
]);

$patient->setCustomFields(['risk-level' => 'high']);
$patient->getCustomFields(); // ['risk-level' => 'high']

// In a controller serving GET /patients?filter[cf_risk-level]=high
$options = CustomFields::queryOptionsFor(Patient::class);
$patients = QueryBuilder::for(Patient::class)
    ->allowedFilters(...$options['filters'])
    ->allowedSorts(...$options['sorts'])
    ->get();
```

The slug `risk-level` is generated once from the name and never changes. It is the key you
read, write, filter and sort by.

## A worked example

Three patients, the `risk-level` field above, a `date-of-birth` field of type `date`, an
`allergies` field of type `multiselect` with the options `pollen` and `latex`, and the
values their records hold:

| Patient | `risk-level` | `date-of-birth` | `allergies` |
| --- | --- | --- | --- |
| Anna Rossi | `high` | 1981-04-02 | `['pollen']` |
| Bruno Neri | `high` | 1974-11-20 | `['pollen', 'latex']` |
| Carla Verdi | `low` | 1990-06-30 | no value |

One controller serves all of it:

```php
use App\Models\Patient;
use PlinCode\CustomFields\Facades\CustomFields;
use Spatie\QueryBuilder\QueryBuilder;

public function index()
{
    $options = CustomFields::queryOptionsFor(Patient::class);

    return QueryBuilder::for(Patient::class)
        ->allowedFilters(...$options['filters'])
        ->allowedSorts(...$options['sorts'])
        ->paginate();
}
```

What the client sends, and what comes back:

| Request | Result |
| --- | --- |
| `?filter[cf_risk-level]=high` | Anna Rossi, Bruno Neri |
| `?filter[cf_allergies:contains_any]=latex` | Bruno Neri |
| `?filter[cf_allergies:contains_all]=pollen,latex` | Bruno Neri |
| `?filter[cf_date-of-birth:between]=1980-01-01,1995-12-31` | Anna Rossi, Carla Verdi |
| `?filter[cf_allergies:is_null]=1` | Carla Verdi |
| `?filter[cf_risk-level]=high&sort=cf_date-of-birth` | Bruno Neri, Anna Rossi |
| `?sort=-cf_date-of-birth` | Carla Verdi, Anna Rossi, Bruno Neri |

Two rows are worth reading twice. `contains_any=latex` returns only Bruno because Anna
holds `pollen` alone, and `is_null=1` returns Carla because she has no `allergies` row at
all. The last row sorts every patient by date of birth descending, and nobody is dropped
for lacking a value.

A field only exposes the operations its type declares, so nothing arbitrary can be built
from a request. The ten operations and their rules are in [querying](https://github.com/plin-code/laravel-custom-fields/blob/main/docs/querying.md).

## Why not a JSON column

A JSON column on the host table, for example with
[spatie/laravel-schemaless-attributes](https://github.com/spatie/laravel-schemaless-attributes),
is simpler and is the right choice when you only store and read the values back. This
package stores one row per field per record, in a column typed after the field. That buys
filtering and sorting that compare integers as integers and dates as dates, one query API
that runs on SQLite, MySQL and PostgreSQL, and option keys that stay stable while labels
change. The cost is a `whereHas` subquery per filter and a correlated subquery per sort,
and `contains` is a `LIKE %x%` that no index can serve.

## Requirements

- PHP ^8.4.
- Laravel 12 or 13.
- `spatie/laravel-query-builder`, which provides the `AllowedFilter` and `AllowedSort`
  instances the package builds.
- `plin-code/laravel-eloquent-sorts`, which validates the sort direction through
  `Direction::normalise()` and gives you relation, relation count and enum sorts that
  compose with the custom field sorts.
- `plin-code/laravel-sql-dialect`, which builds the `contains` match as `ILIKE` on
  PostgreSQL and `LIKE` elsewhere, with the wildcards of the term escaped.
- `spatie/laravel-package-tools`, which wires the config, the translations and the
  migrations.

All four are installed with the package. CI runs the suite on PHP 8.4 and 8.5 against
SQLite, and on PHP 8.4 against MySQL 8.4 and PostgreSQL 17.

## Documentation

- [Installation and configuration](https://github.com/plin-code/laravel-custom-fields/blob/main/docs/installation.md):
  publishing, migrations, key types, translations, every config key, replacing the models.
- [Defining fields](https://github.com/plin-code/laravel-custom-fields/blob/main/docs/defining-fields.md):
  registering an entity, the twelve field types, slugs, options, deactivating a field.
- [Reading and writing values](https://github.com/plin-code/laravel-custom-fields/blob/main/docs/values.md):
  the value API, partial and complete validation, the rules of each type, exceptions, forms.
- [Querying](https://github.com/plin-code/laravel-custom-fields/blob/main/docs/querying.md):
  the `whereCustomField()` scope, request filters and sorts, the operations, invalid values.
- [Custom field types](https://github.com/plin-code/laravel-custom-fields/blob/main/docs/custom-field-types.md):
  the `FieldType` contract, events, and the Laravel Boost resources.
- [Upgrading](https://github.com/plin-code/laravel-custom-fields/blob/main/docs/upgrading.md):
  behaviour changes from 0.3.0 to 1.0.0 for an application on 0.1 or 0.2.

## Limits and security

- `CustomField` and `CustomFieldValue` declare `$fillable`, so an unknown key in `create()` is
  discarded (or throws with `Model::preventSilentlyDiscardingAttributes()`). Validating the
  values and authorizing the request still belong to your application.
- Authorization is your job. Nothing checks who may define, read or write a field.
- `registerEntity()` adds the key to `Relation::morphMap()`, so it becomes the model's
  `getMorphClass()` for every polymorphic relation, not only for custom fields.
- The shipped migration indexes `value_string`, `value_boolean`, `value_integer`,
  `value_decimal`, `value_date` and `value_datetime`, each together with the field. An
  install that ran an earlier migration adds the four newer ones, see
  [upgrading](https://github.com/plin-code/laravel-custom-fields/blob/main/docs/upgrading.md).
  `value_text` and `value_json` are not indexed.
- Deleting a host model deletes its values, but a bulk delete through the query builder
  fires no model events and leaves them behind.
- An invalid filter value throws `PlinCode\CustomFields\Exceptions\InvalidFilterValueException`,
  a Spatie `InvalidFilterValue` that Laravel renders as a 400 out of the box.
- Stored values are not encrypted.
- On SQLite a `decimal` has floating point affinity, so very long values lose precision.

## Testing

```bash
composer test
```

That runs PHPStan, the Pint check, the Rector dry run, the type coverage check and the Pest
suite. The suite uses in memory SQLite by default; `DB_DRIVER=mysql` or `DB_DRIVER=pgsql`
points it at a server.

## Changelog

Please see the [changelog](https://github.com/plin-code/laravel-custom-fields/blob/main/CHANGELOG.md) for what has changed recently.

## Contributing

Thank you for considering contributing to Laravel Custom Fields! Please review our [contributing guide](https://github.com/plin-code/laravel-custom-fields/blob/main/.github/CONTRIBUTING.md) to get started.

## Security Vulnerabilities

Please review [our security policy](https://github.com/plin-code/laravel-custom-fields/blob/main/.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Daniele Barbaro](https://github.com/danielebarbaro)
- [plin-code](https://github.com/plin-code)
- [All Contributors](https://github.com/plin-code/laravel-custom-fields/contributors)

## License

Laravel Custom Fields is open-sourced software licensed under the [MIT license](https://github.com/plin-code/laravel-custom-fields/blob/main/LICENSE.md).
