# Defining fields

A field definition is a `PlinCode\CustomFields\Models\CustomField` row that your product
creates at runtime, usually from an admin screen. This page covers registering the models
that own fields, creating definitions, the twelve built in types, slugs, options, retiring a
field, and the events a definition fires.

## Registering an entity

A model can own custom fields once it uses the trait and is registered under a short entity
key. The key is stored in the `entity_type` column of every definition and is added to the
Eloquent morph map, so registration has to happen on every request. The right place is the
`boot()` method of a service provider:

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

The third argument is the human label shown in your own admin. Leave it out and the
package derives one from the class name. `CustomFields::entities()` returns the registry,
keyed by class, and `CustomFields::entityKey(Patient::class)` returns `'patient'`.

Registration is strict:

- Registering the same model under the same key again is a no op.
- Registering a model that is already registered under a different key throws an
  `InvalidArgumentException`.
- Registering a second model under a key that is already taken throws an
  `InvalidArgumentException`.
- Registering a key that `Relation::morphMap()` already assigns to another class throws an
  `InvalidArgumentException`, because overwriting it would make every stored `morphTo` with
  that alias resolve to the wrong model.

Every call that resolves the entity of a model (`getCustomFields()`, `setCustomFields()`,
`whereCustomField()`, `CustomFields::filtersFor()`, `CustomFields::validate()` and the rest)
throws an `InvalidArgumentException` when the model is not registered. The key also becomes
the `getMorphClass()` of the model for every polymorphic relation, see
[the morph map side effect](installation.md#the-morph-map-side-effect).

> [!WARNING]
> `CustomFields::filtersFor()`, `CustomFields::sortsFor()` and
> `CustomFields::queryOptionsFor()` read the definitions from the database. Call them
> while serving a request, never from `register()` or `boot()`, where the connection and
> the tenant context are not settled yet.

## Creating a definition

Nothing checks `entity_type` against the registry, so resolve it with `entityKey()` rather
than typing the string. A typo creates a definition that every read, write, filter and sort
ignores, with no error anywhere.

`CustomField` uses `$guarded = []`. Build the attributes yourself, or validate the request
before you hand it to `create()`.

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

The other pages of this documentation reuse these four definitions.

The columns of a definition are:

| Column | Meaning |
| --- | --- |
| `entity_type` | The entity key registered with `registerEntity()`. It cannot be changed afterwards. |
| `name` | The human name. Trimmed and lowercased on write, unique per entity, never empty. |
| `slug` | Generated from the name on creation unless you pass one, unique per entity, at most 100 characters. It cannot be changed afterwards. See [slugs](#slugs). |
| `type` | One of the keys in the [field types](#field-types) table, or the key of a type you registered. It cannot be changed once values exist. |
| `options` | The option list of a `select` or a `multiselect`. See [options](#options). |
| `is_required` | Whether [complete validation](values.md#partial-and-complete-validation) demands a value. |
| `is_active` | Whether the field takes part in ordinary reads, writes, filters and sorts. See [deactivating a field](#deactivating-a-field). |
| `sort_order` | The order of the fields. Defaults to `0`. |

`sort_order` is read. `getCustomFields()`, `CustomFields::filtersFor()`,
`CustomFields::sortsFor()`, `CustomFields::queryOptionsFor()` and the validator all order
the definitions by `sort_order` and then by slug. With the four definitions above, where
only `risk-level` sets a `sort_order`, the order is `allergies`, `date-of-birth`, `notes`,
`risk-level`.

The `type` is resolved through the type registry when a definition is created, and again on
update when it changes, so a typo such as `multi_select` throws an `InvalidArgumentException`
at save time.

Two definitions of one entity with the same name hit a unique index and surface as an
`Illuminate\Database\UniqueConstraintViolationException`, since the uniqueness is enforced
by the database and not by the observer.

## Slugs

The slug is the key your application uses everywhere: in `setCustomFields()`, in the array
`getCustomFields()` returns, and in the filter and sort names a client sends.

A generated slug is the name passed through Laravel's slug helper, so `Date of birth`
becomes `date-of-birth`. A name that slugs to nothing becomes `field`. When the slug is
already taken within the entity, a suffix is appended (`notes-2`, `notes-3`), and the
result is cut to stay within 100 characters.

You may pass an explicit `slug` to `CustomField::create()`. It has to match
`/^[a-z0-9][a-z0-9_-]{0,99}$/`: lowercase letters, digits, dashes and underscores, starting
with a letter or a digit, at most 100 characters. Anything else throws an
`InvalidArgumentException`, which keeps out the dots and asterisks the validator would read
as nested keys and the colon that separates a filter name from its operation. An explicit
slug is not deduplicated, so a taken one fails on the unique index.

## Field types

Twelve types are registered out of the box. The `Key` column is the exact string you put in
the `type` column of a definition. Note that the multiple select key is `multiselect` and
not `multi_select`.

| Key | Label | Storage column | Input hint | Filter operations | Sortable |
| --- | --- | --- | --- | --- | --- |
| `text` | Text | `value_string` | `text` | `equals`, `in`, `contains`, `is_null`, `is_not_null` | yes |
| `textarea` | Textarea | `value_text` | `textarea` | `equals`, `contains`, `is_null`, `is_not_null` | no |
| `email` | Email | `value_string` | `text` | `equals`, `in`, `contains`, `is_null`, `is_not_null` | yes |
| `url` | URL | `value_string` | `text` | `equals`, `in`, `contains`, `is_null`, `is_not_null` | yes |
| `phone` | Phone | `value_string` | `text` | `equals`, `in`, `contains`, `is_null`, `is_not_null` | yes |
| `number` | Number | `value_integer` | `number` | `equals`, `in`, `greater_than`, `less_than`, `between`, `is_null`, `is_not_null` | yes |
| `decimal` | Decimal | `value_decimal` | `number` | `equals`, `in`, `greater_than`, `less_than`, `between`, `is_null`, `is_not_null` | yes |
| `boolean` | Boolean | `value_boolean` | `checkbox` | `equals`, `is_null`, `is_not_null` | yes |
| `date` | Date | `value_date` | `date` | `equals`, `greater_than`, `less_than`, `between`, `is_null`, `is_not_null` | yes |
| `datetime` | Date and time | `value_datetime` | `datetime-local` | `equals`, `greater_than`, `less_than`, `between`, `is_null`, `is_not_null` | yes |
| `select` | Select | `value_string` | `select` | `equals`, `in`, `is_null`, `is_not_null` | yes |
| `multiselect` | Multiple select | `value_json` | `multiselect` | `contains_any`, `contains_all`, `is_null`, `is_not_null` | no |

A read returns `null` when nothing is stored, for every type except `multiselect`, whose
default is an empty array. What each type accepts and how it normalises a value is in
[validation rules per type](values.md#validation-rules-per-type), and what each operation
does is in [the operations](querying.md#the-operations). To add a type of your own, see
[custom field types](custom-field-types.md).

## Options

A `select` and a `multiselect` carry an option list. Every option has a stable `key`, a
`label` you can rename freely, and an `is_active` flag. An option without a key or a label,
or two options with the same key, throw an `InvalidArgumentException` when the definition
is saved.

```php
$riskLevel->updateOptions([
    ['key' => 'low', 'label' => 'Low risk', 'is_active' => true],
    ['key' => 'high', 'label' => 'High risk', 'is_active' => true],
    ['key' => 'legacy', 'label' => 'Legacy', 'is_active' => false],
    ['key' => 'critical', 'label' => 'Critical', 'is_active' => true],
]);
```

New keys can be appended and labels can change. An existing key can be deactivated but
never removed or reused. Removing one throws an `InvalidArgumentException`, through
`updateOptions()` and through a plain `update()` alike, because a stored value would lose
its meaning.

An inactive option stays readable. A record that already holds it keeps it and can save it
again, while any other record is rejected with the `invalid_option` message. Inactive
options are excluded from `optionsForInput()`, so your form never offers them.

```php
$riskLevel->optionsForInput();  // the active options, ready for a form
$riskLevel->activeOptionKeys(); // ['low', 'high', 'critical']
$riskLevel->optionKeys();       // ['low', 'high', 'legacy', 'critical']
```

## Deactivating a field

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
| `CustomFields::filtersFor()`, `CustomFields::sortsFor()`, `CustomFields::queryOptionsFor()` | The field is left out, so a request cannot filter or sort on it. |
| `whereCustomField()` | Throws `UnknownCustomFieldException`. The scope takes no opt in. Read the stored values with `getCustomFields(includeInactive: true)` instead. |

## Deleting a field

Deleting a definition removes its values through the `cascadeOnDelete` foreign key on
`custom_field_id`. The database does the work, so no `CustomFieldValueDeleted` event is
dispatched for those rows. If you need an event per value, delete them through Eloquent
first, or deactivate the field instead:

```php
$riskLevel->values()->get()->each->delete(); // one CustomFieldValueDeleted per row
$riskLevel->delete();
```

## Events

A definition dispatches three events, all of them after the surrounding transaction
commits, since each implements `Illuminate\Contracts\Events\ShouldDispatchAfterCommit`:

| Event | Property | Dispatched when |
| --- | --- | --- |
| `PlinCode\CustomFields\Events\CustomFieldCreated` | `$field` | A definition is created. |
| `PlinCode\CustomFields\Events\CustomFieldUpdated` | `$field` | A definition is updated, including through `updateOptions()`. |
| `PlinCode\CustomFields\Events\CustomFieldDeleted` | `$field` | A definition is deleted. Its values go with it and fire no event. |

`$field` is a public readonly `CustomField`. The value side fires two more events, described
in [reading and writing values](values.md#events).

## Factories

`PlinCode\CustomFields\Database\Factories\CustomFieldFactory` builds a `text` definition
with a random name for your tests. The models do not use `HasFactory`, so call the factory
directly and pass the entity key, which defaults to `article`:

```php
use App\Models\Patient;
use PlinCode\CustomFields\Database\Factories\CustomFieldFactory;
use PlinCode\CustomFields\Facades\CustomFields;

$field = CustomFieldFactory::new()->create([
    'entity_type' => CustomFields::entityKey(Patient::class),
    'type' => 'number',
]);
```

`PlinCode\CustomFields\Database\Factories\CustomFieldValueFactory` is described in
[reading and writing values](values.md#factories).
