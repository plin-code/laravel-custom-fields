# Installation and configuration

This page covers installing the package, publishing its resources, running the migrations
in the right order, and every configuration key. Read it before the first `php artisan migrate`,
because two of the keys are baked into the tables and cannot be changed afterwards without
a data migration you write yourself.

## Installing

```bash
composer require plin-code/laravel-custom-fields
```

The package requires PHP 8.4 or newer and supports Laravel 12 and 13. Three packages are
installed with it, and all three are hard requirements:

- `spatie/laravel-package-tools` wires the service provider, the config file, the
  translations and the migrations.
- `spatie/laravel-query-builder` provides the `Spatie\QueryBuilder\AllowedFilter` and
  `Spatie\QueryBuilder\AllowedSort` instances that `CustomFields::filtersFor()` and
  `CustomFields::sortsFor()` return.
- `plin-code/laravel-eloquent-sorts` validates the sort direction inside the custom field
  sort through `PlinCode\EloquentSorts\Support\Direction::normalise()`. It also gives you
  relation, relation count and enum sorts that compose with the custom field sorts, see
  [composing with other sorts](querying.md#composing-with-other-sorts).

The service provider and the `CustomFields` facade alias are discovered automatically.

## Publishing

You may publish the config file, the migrations and the translations at once:

```bash
php artisan vendor:publish --tag="laravel-custom-fields"
```

Or each resource on its own:

```bash
php artisan vendor:publish --tag="laravel-custom-fields-config"
php artisan vendor:publish --tag="laravel-custom-fields-migrations"
php artisan vendor:publish --tag="laravel-custom-fields-lang"
```

## Migrations

```bash
php artisan vendor:publish --tag="laravel-custom-fields-migrations"
php artisan migrate
```

Two migrations are published, `create_custom_fields_table` and
`create_custom_field_values_table`. They are published one file at a time and receive a
fresh timestamp, so they run after the migrations already in your application. Publish
them after the tables of the models that will own custom fields exist.

> [!IMPORTANT]
> Set `key_type` and `morph_key_type` in the published configuration **before** you run
> the migrations. Each accepts `id`, `uuid` or `ulid`, and each defaults to `id`.
> Changing either one once the tables exist requires a data migration you write yourself.

`key_type` is the primary key of the two package tables. `uuid` generates time ordered
UUID v7 keys. The `custom_field_id` foreign key on the values table follows the same type.

`morph_key_type` is the type of the `valuable_id` column, which holds the key of the model
that owns a value. It must match the key type of every model you attach custom fields to:
keep `id` for a default Laravel model, switch to `uuid` or `ulid` only when every such
model uses that key type. There is no foreign key on `valuable_id`, so a mismatch is not
caught by the database.

The values table carries a unique index on the field and the owner, and two composite
indexes, on `value_string` and on `value_boolean`. `value_integer`, `value_decimal`,
`value_date` and `value_datetime` are not indexed. If you filter or sort on them at scale,
add an index in a migration of your own, for example on `custom_field_id` and
`value_date`.

## Translations

```bash
php artisan vendor:publish --tag="laravel-custom-fields-lang"
```

The package ships English and Italian validation messages under the
`laravel-custom-fields` translation namespace, for an unknown field, an inactive field and
an option that cannot be assigned. Published files land in
`lang/vendor/laravel-custom-fields`. The required message is Laravel's own
`validation.required`, so it follows your application locale.

## Configuration reference

| Key | Default | What it does |
| --- | --- | --- |
| `tables.fields` | `custom_fields` | Table holding the field definitions. |
| `tables.values` | `custom_field_values` | Table holding one row per field per record. |
| `key_type` | `id` | Primary key type of both package tables. Accepts `id`, `uuid`, `ulid`. `uuid` generates UUID v7. Must be chosen before migrating. |
| `morph_key_type` | `id` | Key type of the models that own custom fields, used for the `valuable_id` column. Accepts `id`, `uuid`, `ulid`. Must be chosen before migrating. |
| `models.custom_field` | `PlinCode\CustomFields\Models\CustomField` | The definition model. Point it at your own subclass to add relations, casts, a global scope or a dedicated connection. |
| `models.custom_field_value` | `PlinCode\CustomFields\Models\CustomFieldValue` | The value model. Same rule. |
| `key_prefix` | `cf_` | Prefix of every filter and sort name the package exposes to a request. |

An unsupported value in `key_type` or `morph_key_type` throws an
`InvalidArgumentException` while the service provider registers, so a typo fails at boot
and not at the first query.

## Replacing the models

A replacement model must extend the package model it replaces, because the package calls
its methods (`fieldType()`, `optionKeys()`, `getValue()` and the rest) on whatever class
the configuration names.

```php
use PlinCode\CustomFields\Models\CustomFieldValue;

class TenantCustomFieldValue extends CustomFieldValue
{
    protected static function booted(): void
    {
        parent::booted();

        static::addGlobalScope(new TenantScope());
    }
}
```

Scopes of the configured value model apply to reads, writes and the custom field sort, so a
tenant scope keeps holding everywhere. Resolve the classes through the package rather than
referencing them directly, so your code keeps following the configuration:

```php
use PlinCode\CustomFields\Facades\CustomFields;

$fieldModel = CustomFields::fieldModel();
$valueModel = CustomFields::valueModel();
```

## The morph map side effect

`CustomFields::registerEntity(Patient::class, 'patient')` adds `'patient'` to
`Illuminate\Database\Eloquent\Relations\Relation::morphMap()`. From then on `'patient'` is
the `getMorphClass()` of `Patient` for every polymorphic relation in your application, not
only for custom fields. If the model already takes part in polymorphic relations stored
with the full class name, new rows will carry the key while the old ones keep the class
name, so migrate their `*_type` columns to the key. Registering a key that the morph map already assigns to
another class throws an `InvalidArgumentException`. See
[registering an entity](defining-fields.md#registering-an-entity).
