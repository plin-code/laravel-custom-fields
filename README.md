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

Typed, extensible custom fields for Eloquent models with filtering and sorting.

Your product defines the fields at runtime, your users fill them in, and the package
stores every value in a typed column, validates it, and exposes it to a request as a
filter and as a sort. The package is headless. It ships no controllers, no routes, no
authorization and no UI.

## Contents

- [Installation](#installation)
- [Configuration](#configuration)
- [Registering an entity](#registering-an-entity)
- [Defining fields](#defining-fields)
- [Field types](#field-types)
- [Reading and writing values](#reading-and-writing-values)
- [Validation](#validation)
- [Exceptions](#exceptions)
- [Options](#options)
- [Querying](#querying)
- [Building a form](#building-a-form)
- [Events](#events)
- [Custom field types](#custom-field-types)
- [Testing](#testing)

## Installation

You can install the package via Composer:

```bash
composer require plin-code/laravel-custom-fields
```

The package requires PHP 8.4 or newer and supports Laravel 12 and 13.

Three packages are installed with it:

- `spatie/laravel-package-tools` wires the service provider, the config file, the
  translations and the migrations.
- `spatie/laravel-query-builder` provides the `AllowedFilter` and `AllowedSort` classes.
  `CustomFields::filtersFor()` and `CustomFields::sortsFor()` return instances of them,
  so you need it only when you expose custom fields to an HTTP request.
- `plin-code/laravel-eloquent-sorts` is used for one thing, `Direction::normalise()`,
  which validates the direction inside the custom field sort. Having it installed also
  gives you the relation, relation count and enum sorts of that package, which compose
  with the custom field sorts in the same `allowedSorts()` call. See
  [composing with other sorts](#composing-with-other-sorts).

You may publish all of the package's resources at once:

```bash
php artisan vendor:publish --tag="laravel-custom-fields"
```

Or, you may publish each resource individually:

### Publishing the Configuration File

```bash
php artisan vendor:publish --tag="laravel-custom-fields-config"
```

### Publishing and Running the Migrations

```bash
php artisan vendor:publish --tag="laravel-custom-fields-migrations"
php artisan migrate
```

Two migrations are published, `create_custom_fields_table` and
`create_custom_field_values_table`. They are published one file at a time and receive a
fresh timestamp, so they run after the migrations already in your application.

> [!IMPORTANT]
> Set `key_type` and `morph_key_type` in the published configuration **before** you run
> the migrations. Each accepts `id`, `uuid` or `ulid`, and each defaults to `id`.
> Changing either one once the tables exist requires a data migration you write yourself.

### Publishing the Translations

```bash
php artisan vendor:publish --tag="laravel-custom-fields-lang"
```

The package ships English and Italian validation messages under the
`laravel-custom-fields` translation namespace. Published files land in
`lang/vendor/laravel-custom-fields`.

## Configuration

| Key | Default | What it does |
| --- | --- | --- |
| `tables.fields` | `custom_fields` | Table holding the field definitions. |
| `tables.values` | `custom_field_values` | Table holding one row per field per record. |
| `key_type` | `id` | Primary key type of both package tables. Accepts `id`, `uuid`, `ulid`. Must be chosen before migrating. |
| `morph_key_type` | `id` | Key type of the models that own custom fields, used for the `valuable_id` column. Accepts `id`, `uuid`, `ulid`. Must be chosen before migrating. |
| `models.custom_field` | `PlinCode\CustomFields\Models\CustomField` | The definition model. Point it at your own subclass to add relations, casts, a global scope or a dedicated connection. |
| `models.custom_field_value` | `PlinCode\CustomFields\Models\CustomFieldValue` | The value model. Same rule. |
| `key_prefix` | `cf_` | Prefix of every filter and sort name the package exposes to a request. |

An unsupported value in `key_type` or `morph_key_type` throws an
`InvalidArgumentException` while the service provider registers, so a typo fails at boot
and not at the first query.

If you replace either model, resolve it through the package rather than referencing the
class directly, so your code keeps following the configuration:

```php
use PlinCode\CustomFields\Facades\CustomFields;

$fieldModel = CustomFields::fieldModel();
$valueModel = CustomFields::valueModel();
```

## Registering an entity

A model can own custom fields once it is registered under a short entity key. The key is
stored in the `entity_type` column and is added to the Eloquent morph map, so registration
has to happen on every request. The right place is the `boot()` method of a service
provider:

```php
<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Patient;
use Illuminate\Support\ServiceProvider;
use PlinCode\CustomFields\Facades\CustomFields;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        CustomFields::registerEntity(Patient::class, 'patient', 'Patient');
    }
}
```

The third argument is the human label shown in your own admin. Leave it out and the
package derives one from the class name. Registering the same model twice is a no op,
while registering two models under the same key throws an `InvalidArgumentException`.

Add the trait to the model:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use PlinCode\CustomFields\Concerns\HasCustomFields;

class Patient extends Model
{
    use HasCustomFields;
}
```

> [!WARNING]
> `CustomFields::filtersFor()`, `CustomFields::sortsFor()` and
> `CustomFields::queryOptionsFor()` read the definitions from the database. Call them
> while serving a request, never from `register()` or `boot()`, where the connection and
> the tenant context are not settled yet.

## Defining fields

Definitions are rows your product creates. A name is trimmed and stored in lowercase, and
the slug is generated once from the name and never changes afterwards. The slug is the key
your application uses everywhere.

Nothing checks `entity_type` against the registry, so resolve it with `entityKey()` rather
than typing the string. A typo creates a definition that every read, write, filter and sort
ignores, with no error anywhere.

```php
use App\Models\Patient;
use PlinCode\CustomFields\Facades\CustomFields;
use PlinCode\CustomFields\Models\CustomField;

$patientKey = CustomFields::entityKey(Patient::class); // 'patient'

$riskLevel = CustomField::create([
    'entity_type' => $patientKey,
    'name' => '  Risk level  ',
    'type' => 'select',
    'is_required' => true,
    'sort_order' => 10,
    'options' => [
        ['key' => 'low', 'label' => 'Low', 'is_active' => true],
        ['key' => 'high', 'label' => 'High', 'is_active' => true],
        ['key' => 'legacy', 'label' => 'Legacy', 'is_active' => false],
    ],
]);

$riskLevel->name; // 'risk level'
$riskLevel->slug; // 'risk-level'

CustomField::create([
    'entity_type' => $patientKey,
    'name' => 'Date of birth',
    'type' => 'date',
]);

CustomField::create([
    'entity_type' => $patientKey,
    'name' => 'Allergies',
    'type' => 'multiselect',
    'options' => [
        ['key' => 'pollen', 'label' => 'Pollen', 'is_active' => true],
        ['key' => 'latex', 'label' => 'Latex', 'is_active' => true],
    ],
]);

CustomField::create([
    'entity_type' => $patientKey,
    'name' => 'Notes',
    'type' => 'textarea',
]);
```

The columns of a definition are:

| Column | Meaning |
| --- | --- |
| `entity_type` | The entity key registered with `registerEntity()`. |
| `name` | The human name. Trimmed and lowercased on write, unique per entity. |
| `slug` | Generated from the name on creation, unique per entity, at most 100 characters. It cannot be changed afterwards. |
| `type` | One of the keys in the [field types](#field-types) table. It cannot be changed once values exist. |
| `options` | The option list of a `select` or a `multiselect`. See [options](#options). |
| `is_required` | Whether [complete validation](#validation) demands a value. |
| `is_active` | Whether the field takes part in ordinary reads, writes, filters and sorts. |
| `sort_order` | A hint your product can order its form by. The package stores it and does not order anything by it. |

## Field types

Twelve types are registered out of the box. The `Key` column is the exact string you put
in the `type` column of a definition.

| Key | Label | Storage column | Input hint | Query operations |
| --- | --- | --- | --- | --- |
| `text` | Text | `value_string` | `text` | `equals`, `in`, `contains`, `is_null`, `is_not_null`, `sort` |
| `textarea` | Textarea | `value_text` | `textarea` | `equals`, `contains`, `is_null`, `is_not_null` |
| `email` | Email | `value_string` | `text` | `equals`, `in`, `contains`, `is_null`, `is_not_null`, `sort` |
| `url` | URL | `value_string` | `text` | `equals`, `in`, `contains`, `is_null`, `is_not_null`, `sort` |
| `phone` | Phone | `value_string` | `text` | `equals`, `in`, `contains`, `is_null`, `is_not_null`, `sort` |
| `number` | Number | `value_integer` | `number` | `equals`, `in`, `greater_than`, `less_than`, `between`, `is_null`, `is_not_null`, `sort` |
| `decimal` | Decimal | `value_decimal` | `number` | `equals`, `in`, `greater_than`, `less_than`, `between`, `is_null`, `is_not_null`, `sort` |
| `boolean` | Boolean | `value_boolean` | `checkbox` | `equals`, `is_null`, `is_not_null`, `sort` |
| `date` | Date | `value_date` | `date` | `equals`, `greater_than`, `less_than`, `between`, `is_null`, `is_not_null`, `sort` |
| `datetime` | Date and time | `value_datetime` | `datetime-local` | `equals`, `greater_than`, `less_than`, `between`, `is_null`, `is_not_null`, `sort` |
| `select` | Select | `value_string` | `select` | `equals`, `in`, `is_null`, `is_not_null`, `sort` |
| `multiselect` | Multiple select | `value_json` | `multiselect` | `contains_any`, `contains_all`, `is_null`, `is_not_null` |

Note that the multiple select key is `multiselect` and not `multi_select`.

The validation rules each type applies to a submitted value:

| Key | Rules |
| --- | --- |
| `text`, `phone` | `nullable`, `string`, `max:255` |
| `textarea` | `nullable`, `string` |
| `email` | `nullable`, `email`, `max:255` |
| `url` | `nullable`, `url`, `max:255` |
| `number` | `nullable`, `integer` |
| `decimal` | `nullable`, `numeric` |
| `boolean` | `nullable`, `boolean` |
| `date`, `datetime` | `nullable`, `date` |
| `select` | `nullable`, `string`, and the value has to be an assignable option key |
| `multiselect` | `nullable`, `array`, and every element has to be an assignable option key |

The default a read returns when nothing is stored is `null` for every type except
`multiselect`, whose default is an empty array.

## Reading and writing values

```php
$patient->setCustomFields([
    'risk-level' => 'high',
    'date-of-birth' => '1985-03-02',
    'allergies' => ['pollen', 'latex'],
]);

$patient->getCustomFields();
// [
//     'risk-level' => 'high',
//     'date-of-birth' => '1985-03-02',
//     'allergies' => ['pollen', 'latex'],
//     'notes' => null,
// ]

$patient->getCustomField('risk-level'); // 'high'
$patient->setCustomField('notes', 'Follow up in June.');
$patient->clearCustomField('notes');
```

`setCustomFields()` validates the whole batch first and then writes it inside a single
transaction, so a rejected batch leaves nothing behind. `setCustomField()` is a single key
call onto the same path. Both refuse a model that has not been saved yet and throw a
`ModelNotPersistedException` without issuing a query.

`getCustomFields()` returns every active field of the entity keyed by slug, including the
fields with no stored value, so the array shape is stable and safe to hand to a form.

### Clearing a value

Passing `null` deletes the stored row. Reading the field afterwards returns the default of
its type, and a `CustomFieldValueDeleted` event is dispatched.

```php
$patient->setCustomField('date-of-birth', null); // the row is deleted
$patient->getCustomField('date-of-birth');       // null

$patient->clearCustomField('date-of-birth');     // the same thing, explicitly
```

An empty array is not the same as `null` on a `multiselect`. An empty array is an answered
field, so the row is kept and the value reads back as `[]`. Only `null` deletes the row.

```php
$patient->setCustomField('allergies', []);   // row kept, reads back as []
$patient->setCustomField('allergies', null); // row deleted, reads back as []
```

### Deactivated definitions

Setting `is_active` to `false` on a definition retires it without destroying the data
already stored. The behaviour is not uniform across the API, because a read and a write
have different needs:

| Call | On an inactive definition |
| --- | --- |
| `getCustomFields()` | The field is left out. |
| `getCustomFields(includeInactive: true)` | The field is included. |
| `getCustomField($slug)` | Throws `UnknownCustomFieldException`. |
| `getCustomField($slug, includeInactive: true)` | Returns the stored value. |
| `setCustomField()`, `setCustomFields()` | Throws `ValidationException`. A retired field can never be written again. |
| `clearCustomField($slug)` | Removes the value. No flag is needed, otherwise retired data could never be deleted. |
| `filtersFor()`, `sortsFor()`, `queryOptionsFor()` | The field is left out, so a request cannot filter or sort on it. |
| `whereCustomField()` | Throws `UnknownCustomFieldException`. The scope takes no opt in, so a retired field cannot be queried through it. Read the stored values with `getCustomFields(includeInactive: true)` instead. |

## Validation

The package validates in two modes.

**Partial** is the default. Only the submitted keys are inspected, so a required field that
is absent from the payload is not checked. This is what a PATCH endpoint wants.

**Complete** inspects the state the record ends up with, the values already stored merged
with the changes you submit. A required field that is already stored may be left out of the
payload, while a required field that is explicitly submitted as `null`, or that was never
stored at all, fails.

```php
$patient->setCustomFields(['notes' => 'Follow up.']);                     // partial
$patient->setCustomFields(['notes' => 'Follow up.'], complete: true);     // complete
```

You can validate without writing, which is useful in a form request:

```php
use PlinCode\CustomFields\Facades\CustomFields;

CustomFields::validate($patient, $request->input('custom_fields', []), complete: true);
```

Or take the rule array and merge it into your own rules:

```php
$rules = CustomFields::validator()->rules(Patient::class, complete: true);
// ['risk-level' => ['required', 'string', ...], 'date-of-birth' => ['nullable', 'date'], ...]
```

> [!WARNING]
> The rule array is not equivalent to `CustomFields::validate()`. It carries the rules of
> each type and nothing else, so it does not reject a slug that is unknown or inactive, and
> it accepts an option key that exists but is no longer assignable. `validate()` performs
> both of those checks on top of the rules. Use the array to drive a form request, then let
> the write path validate again, which it always does.

Messages come from the `laravel-custom-fields` translation namespace, except the required
message, which is Laravel's own `validation.required` so it follows your application
locale. Both use the field name rather than the slug as the attribute, so a field named
`date of birth` produces "The date of birth field is required." and not
"The date-of-birth field is required."

A required field needs a value that is actually filled. `null`, an empty string and an
empty array all fail complete validation, which for a required `multiselect` means at least
one option has to be selected. That is a separate question from storage, where an empty
array is still an answered field and keeps its row.

## Exceptions

| Exception | Thrown by | When | Reasonable response |
| --- | --- | --- | --- |
| `Illuminate\Validation\ValidationException` | `setCustomField()`, `setCustomFields()`, `CustomFields::validate()` | A value fails the rules of its type, a required field is missing under complete validation, a submitted slug is unknown, a submitted definition is inactive, or an option key cannot be assigned. Errors are keyed by slug. | 422 |
| `PlinCode\CustomFields\Exceptions\ModelNotPersistedException` | `setCustomField()`, `setCustomFields()` | The host model has no key yet. | 500, it is a programming error |
| `PlinCode\CustomFields\Exceptions\UnknownCustomFieldException` | `getCustomField()`, `clearCustomField()`, `whereCustomField()` | The slug is not defined for the entity. Also when it is inactive, except for `clearCustomField()`, which always works, and for `getCustomField($slug, includeInactive: true)`. `whereCustomField()` has no opt in. | 404 or 500, depending on whether the slug came from a request |
| `InvalidArgumentException` | `CustomFields::type()`, `registerType()`, `registerEntity()`, `entityKey()`, `updateOptions()`, and the definition observer | An unknown type key, a type key registered twice, a duplicated entity key, an unregistered model, an option removal, an empty name, a duplicated option key, a slug or entity change, or a type change while values exist. | 500 |
| `Illuminate\Database\UniqueConstraintViolationException` | `CustomField::create()` | Two definitions of the same entity are given the same name. The uniqueness is enforced by the database, not by the observer, so it surfaces as a query exception. | 409 or 422, after you catch it |

`UnknownCustomFieldException` extends `InvalidArgumentException` and
`ModelNotPersistedException` extends `RuntimeException`, so an existing catch on the parent
class keeps matching.

Because a slug that arrives from a request is a client mistake and not a server one, the
write path reports an unknown or inactive slug as a validation error rather than as an
exception you have to translate yourself:

```php
use Illuminate\Validation\ValidationException;

try {
    $patient->setCustomFields($request->input('custom_fields', []));
} catch (ValidationException $e) {
    $e->errors(); // ['risk-level' => ['The selected option legacy is invalid for risk level.']]
}
```

## Options

A `select` and a `multiselect` carry an option list. Every option has a stable `key`, a
`label` you can rename freely, and an `is_active` flag.

```php
$riskLevel->updateOptions([
    ['key' => 'low', 'label' => 'Low risk', 'is_active' => true],
    ['key' => 'high', 'label' => 'High risk', 'is_active' => true],
    ['key' => 'legacy', 'label' => 'Legacy', 'is_active' => false],
    ['key' => 'critical', 'label' => 'Critical', 'is_active' => true],
]);
```

New keys can be appended and labels can change. An existing key can be deactivated but
never removed or reused, and trying to remove one throws an `InvalidArgumentException`,
because a stored value would lose its meaning.

An inactive option stays readable. A record that already holds it keeps it and can save
it again, while any other record is rejected with the `invalid_option` message. Inactive
options are excluded from `optionsForInput()`, so your form never offers them.

```php
$riskLevel->optionsForInput(); // the active options, ready for a form
$riskLevel->activeOptionKeys(); // ['low', 'high', 'critical']
$riskLevel->optionKeys();       // ['low', 'high', 'legacy', 'critical']
```

## Querying

### A worked example

Three patients, the definitions from [defining fields](#defining-fields), and the values
their records hold:

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

The rest of this section is the reference behind that example.

### The whereCustomField scope

The trait adds a scope for a direct equality lookup:

```php
Patient::whereCustomField('risk-level', 'high')->get();
```

It resolves an active definition, throws `UnknownCustomFieldException` when the slug is
unknown or inactive, and constrains the typed storage column of that field through a
`whereHas` on the value relation. It is equality only, and it passes the value to the query
as it is, so give it the stored shape: an option key for a select, a boolean for a boolean,
a `Y-m-d` string for a date. Everything richer belongs to the request filters below.

### Filters and sorts for a request

`CustomFields::filtersFor()` returns one `AllowedFilter` per declared query operation of
every active field, and `CustomFields::sortsFor()` returns an `AllowedSort` for every
active field whose type declares `sort`. Spatie expects them spread:

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

### The names a client sends

A filter is named after the `key_prefix` and the slug. Equality keeps the bare name, and
every other operation is suffixed with a colon, a character a generated slug never
contains. Sorts always use the bare name.

With the definitions above and the default `cf_` prefix, a client can send the following.
The line breaks are here for readability, a real request sends one line:

```http
GET /api/patients
    ?filter[cf_risk-level]=high
    &filter[cf_risk-level:in]=high,low
    &filter[cf_date-of-birth:between]=1980-01-01,1989-12-31
    &filter[cf_allergies:contains_any]=pollen,latex
    &filter[cf_notes:contains]=follow%20up
    &filter[cf_notes:is_null]=1
    &sort=-cf_date-of-birth
```

You can build the same names in your own code, for a link or for an OpenAPI document:

```php
CustomFields::keyPrefix();                       // 'cf_'
CustomFields::filterName('risk-level');          // 'cf_risk-level'
CustomFields::filterName('risk-level', 'in');    // 'cf_risk-level:in'
CustomFields::sortName('date-of-birth');         // 'cf_date-of-birth'
```

### The operations

| Operation | Value the client sends | Meaning |
| --- | --- | --- |
| `equals` | a single value, or a list | Exact match. A list behaves as an `in`. |
| `in` | a comma separated list | The value is one of the given ones. |
| `contains` | a value, or a comma separated list | Case handling follows the collation of your database. `%`, `_`, `!` and a backslash are matched literally. A list matches any of the values. |
| `greater_than`, `less_than` | a single value | A strict comparison. A list is rejected. |
| `between` | exactly two comma separated values | An inclusive range. Any other count is rejected. |
| `is_null` | `1` to ask for absent, `0` to ask for present | A record with no value row and a record whose typed column is null both count as absent. |
| `is_not_null` | `1` to ask for present, `0` to ask for absent | The complement. A `multiselect` stored as an empty array counts as present. |
| `contains_any` | a comma separated list | The stored JSON array holds at least one of the values. |
| `contains_all` | a comma separated list | The stored JSON array holds all of the values. |

A field only exposes the operations its type declares, so nothing arbitrary can be built
from a request. A `multiselect` never exposes a bare equality filter, and asking for
`filter[cf_allergies]` raises Spatie's `InvalidFilterQuery`. A `greater_than` given an array,
and a `between` given anything other than two values, raise Spatie's `InvalidFilterValue`.

Records without a value for the sorted field come last in both directions, on MySQL,
PostgreSQL and SQLite alike. The sort is expressed as a correlated subquery, so your own
select, aggregates and existing order are left untouched and no row is duplicated.

### Composing with other sorts

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

## Building a form

The package stores the metadata your UI needs and never renders it. Two things are exposed:
the registry of types, for the screen where an administrator defines a field, and the
definitions themselves, for the screen where a user fills them in.

```php
use PlinCode\CustomFields\Facades\CustomFields;

foreach (CustomFields::types() as $key => $type) {
    $choice = [
        'value' => $key,                 // 'select'
        'label' => $type->label(),       // 'Select'
        'input' => $type->inputHint(),   // 'select'
    ];
}
```

`inputHint()` is a suggestion, not a contract. It maps cleanly onto an HTML input type for
most of the built in types (`text`, `number`, `checkbox`, `date`, `datetime-local`) and
names a widget for the rest (`textarea`, `select`, `multiselect`).

```php
use App\Models\Patient;
use PlinCode\CustomFields\Facades\CustomFields;

$fieldModel = CustomFields::fieldModel();

$definitions = $fieldModel::query()
    ->where('entity_type', CustomFields::entityKey(Patient::class))
    ->where('is_active', true)
    ->orderBy('sort_order')
    ->get();

$values = $patient->getCustomFields();

$form = $definitions->map(fn ($field) => [
    'name' => $field->slug,                          // 'risk-level'
    'label' => $field->name,                         // 'risk level'
    'required' => $field->is_required,               // true
    'input' => $field->fieldType()->inputHint(),     // 'select'
    'options' => $field->optionsForInput(),          // [['key' => 'low', 'label' => 'Low', 'is_active' => true], ...]
    'rules' => $field->fieldType()->rules($field),   // ['nullable', 'string', Rule::in([...])]
    'value' => $values[$field->slug],                // 'high'
]);
```

Post the form back keyed by slug and hand the whole array to `setCustomFields()`.

## Events

Five events are dispatched, all of them after the surrounding transaction commits, since
each implements `Illuminate\Contracts\Events\ShouldDispatchAfterCommit`.

| Event | Property | Dispatched when |
| --- | --- | --- |
| `PlinCode\CustomFields\Events\CustomFieldCreated` | `$field` | A definition is created. |
| `PlinCode\CustomFields\Events\CustomFieldUpdated` | `$field` | A definition is updated, including an option list change. |
| `PlinCode\CustomFields\Events\CustomFieldDeleted` | `$field` | A definition is deleted. |
| `PlinCode\CustomFields\Events\CustomFieldValueSaved` | `$value` | A value row is inserted or updated. |
| `PlinCode\CustomFields\Events\CustomFieldValueDeleted` | `$value` | A value row is removed, by `clearCustomField()`, by a `null` write, or by deleting the row yourself. |

`$field` is a `CustomField` and `$value` is a `CustomFieldValue`, both public and readonly.
Use them to invalidate a cache, to reindex a search document, or to write an audit trail.

## Custom field types

A type implements `PlinCode\CustomFields\Contracts\FieldType` and owns its storage column,
its validation rules, its serialization, its default, its labels and the query operations it
declares. Extend one of the built in types when you only need to change part of that.

```php
<?php

declare(strict_types=1);

namespace App\CustomFields;

use Illuminate\Database\Eloquent\Model;
use PlinCode\CustomFields\Types\TextType;

class CountryType extends TextType
{
    public static function key(): string
    {
        return 'country';
    }

    public function rules(Model $field): array
    {
        return ['nullable', 'string', 'size:2'];
    }

    public function label(): string
    {
        return 'Country';
    }

    public function queryOperations(): array
    {
        return ['equals', 'in', 'is_null', 'is_not_null', 'sort'];
    }
}
```

Register it from a service provider, next to your entities:

```php
CustomFields::registerType(CountryType::class);
```

The vocabulary of query operations is `equals`, `in`, `contains`, `greater_than`,
`less_than`, `between`, `is_null`, `is_not_null`, `contains_any`, `contains_all` and `sort`.
A type may declare an operation outside that list, and then it is responsible for providing
the filter that serves it, since the built in filter refuses an operation it does not know.

## Testing

```bash
composer test
```

That runs static analysis, the formatting check, the refactoring check, the type coverage
check and the test suite. Each step is also available on its own:

```bash
composer analyse       # PHPStan
composer lint:check    # Pint, in check mode
composer rector:check  # Rector, as a dry run
composer test:types    # Pest type coverage
composer test:unit     # Pest
```

`composer lint` applies the formatting rather than checking it. Rector has no apply script
on purpose, so a refactoring it proposes is read before it is taken:

```bash
vendor/bin/rector process
```

The suite runs against an in memory SQLite database by default. Point it at a server with
`DB_DRIVER`, which is how the workflow exercises MySQL and PostgreSQL on every build:

```bash
DB_DRIVER=mysql DB_PORT=3306 DB_USERNAME=root vendor/bin/pest
DB_DRIVER=pgsql DB_PORT=5432 DB_USERNAME=postgres DB_PASSWORD=secret vendor/bin/pest
```

`DB_HOST` and `DB_DATABASE` are read the same way and default to `127.0.0.1` and
`custom_fields`.

## Contributing

Thank you for considering contributing to Laravel Custom Fields! Please review our [contributing guide](.github/CONTRIBUTING.md) to get started.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Daniele Barbaro](https://github.com/plin-code)
- [All Contributors](../../contributors)

## License

Laravel Custom Fields is open-sourced software licensed under the [MIT license](LICENSE.md).
