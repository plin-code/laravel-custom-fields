---
name: custom-fields-development
description: Install plin-code/laravel-custom-fields, attach custom fields to an Eloquent model, define fields of every built in type, add a custom field type, and expose custom fields as validated values, request filters and sorts.
---

# Custom fields development

## When to use this skill

Use this skill when a task touches `plin-code/laravel-custom-fields`: installing it, letting
a model own custom fields, creating or changing field definitions, reading or writing values
from a controller or a form request, adding a field type of your own, or filtering and sorting
a listing on custom fields. Every step below follows the package source, so prefer it over
guessing method names.

## Install and migrate

1. Publish the config and decide the key types first, because both are baked into the
   migrations:

```bash
php artisan vendor:publish --tag="laravel-custom-fields-config"
```

2. In `config/laravel-custom-fields.php` set `key_type` (keys of the two package tables) and
   `morph_key_type` (keys of the models that own fields, stored in `valuable_id`). Each accepts
   `id`, `uuid` or `ulid`. Use `morph_key_type => 'uuid'` or `'ulid'` only when every model
   that will own fields uses that key type. An unsupported value throws an
   `InvalidArgumentException` while the service provider registers.
3. Publish and run the two migrations (`create_custom_fields_table`,
   `create_custom_field_values_table`):

```bash
php artisan vendor:publish --tag="laravel-custom-fields-migrations"
php artisan migrate
```

Changing a key type after this point needs a data migration written by hand. The umbrella tag
`laravel-custom-fields` publishes config, migrations and translations at once, and
`laravel-custom-fields-lang` publishes only the English and Italian messages.

## Make a model support custom fields

1. Add the trait to the model. No interface and no extra column are needed on the host table,
   values live in the package table through the polymorphic `valuable` relation.

```php
use Illuminate\Database\Eloquent\Model;
use PlinCode\CustomFields\Concerns\HasCustomFields;

class Patient extends Model
{
    use HasCustomFields;
}
```

2. Register the entity in `boot()` of a service provider, so it runs on every request:

```php
use PlinCode\CustomFields\Facades\CustomFields;

public function boot(): void
{
    CustomFields::registerEntity(Patient::class, 'patient', 'Patient');
}
```

The signature is `registerEntity(string $model, string $key, ?string $label = null)`. The key
is stored in `entity_type` and added to the morph map. Registering the same model twice is
ignored, while reusing a key for another model throws `InvalidArgumentException`.
`CustomFields::entities()` returns the registry and `CustomFields::entityKey($modelOrClass)`
resolves a key, throwing `InvalidArgumentException` for an unregistered model.

3. In a test, register the entity in `beforeEach` (or `setUp`), since the registry lives on a
   singleton that starts empty.

## Define fields of each type

A definition has these columns: `entity_type`, `name` (trimmed and lowercased, unique per
entity), `slug` (generated from the name, at most 100 characters, immutable), `type`,
`options`, `is_required`, `is_active`, `sort_order`.

| `type` | Storage column | Accepts | Query operations (`sort` means it can be sorted on) |
| --- | --- | --- | --- |
| `text`, `phone` | `value_string` | string up to 255 | `equals`, `in`, `contains`, `is_null`, `is_not_null`, `sort` |
| `email` | `value_string` | email up to 255 | same as `text` |
| `url` | `value_string` | URL up to 255 | same as `text` |
| `textarea` | `value_text` | any string | `equals`, `contains`, `is_null`, `is_not_null` |
| `number` | `value_integer` | integer | `equals`, `in`, `greater_than`, `less_than`, `between`, `is_null`, `is_not_null`, `sort` |
| `decimal` | `value_decimal` | numeric, read back as a trimmed string | same as `number` |
| `boolean` | `value_boolean` | boolean | `equals`, `is_null`, `is_not_null`, `sort` |
| `date` | `value_date` | date | `equals`, `greater_than`, `less_than`, `between`, `is_null`, `is_not_null`, `sort` |
| `datetime` | `value_datetime` | date | same as `date` |
| `select` | `value_string` | one assignable option key | `equals`, `in`, `is_null`, `is_not_null`, `sort` |
| `multiselect` | `value_json` | array of assignable option keys | `contains_any`, `contains_all`, `is_null`, `is_not_null` |

Every type reads back `null` when nothing is stored, except `multiselect`, which reads `[]`.

```php
use PlinCode\CustomFields\Facades\CustomFields;
use PlinCode\CustomFields\Models\CustomField;

$entity = CustomFields::entityKey(Patient::class);

CustomField::create(['entity_type' => $entity, 'name' => 'Date of birth', 'type' => 'date']);
CustomField::create(['entity_type' => $entity, 'name' => 'Weight', 'type' => 'decimal', 'sort_order' => 20]);
CustomField::create(['entity_type' => $entity, 'name' => 'Allergies', 'type' => 'multiselect', 'options' => [
    ['key' => 'pollen', 'label' => 'Pollen', 'is_active' => true],
    ['key' => 'latex', 'label' => 'Latex', 'is_active' => true],
]]);
```

Rules the definition observer enforces, each failing with `InvalidArgumentException`:

- an empty name, an option without `key` or `label`, a duplicated option key;
- changing `slug` or `entity_type` after creation;
- changing `type` while values exist;
- removing an option key, whether through `update()` or `$field->updateOptions($options)`.

Two definitions of one entity with the same name hit a unique index and surface as
`Illuminate\Database\UniqueConstraintViolationException`. To retire an option set its
`is_active` to `false`: records that already hold it keep it, new records cannot pick it.
`$field->optionsForInput()` returns the active options, `$field->activeOptionKeys()` their keys
and `$field->optionKeys()` every key. To retire a whole field set `is_active` to `false` on the
definition: it disappears from reads, writes, filters and sorts, its stored values survive and
`getCustomFields(includeInactive: true)` still reads them.

## Read and write values in a controller

Signatures on the trait:

- `getCustomField(string $slug, bool $includeInactive = false): mixed`
- `getCustomFields(bool $includeInactive = false): array`
- `setCustomField(string $slug, mixed $value): void`
- `setCustomFields(array $values, bool $complete = false): void`
- `clearCustomField(string $slug): void`
- `customFieldValues(): MorphMany`

A form posts values keyed by slug. Hand the array straight to the model:

```php
use Illuminate\Http\Request;

public function update(Request $request, Patient $patient)
{
    $patient->setCustomFields($request->input('custom_fields', []), complete: true);

    return $patient->getCustomFields();
}
```

A failed batch throws `ValidationException`, which Laravel turns into a 422 with errors keyed
by slug, and nothing is written. To validate earlier, for example in a form request, call
`CustomFields::validate($patient, $values, complete: true)`. To merge the type rules into your
own rule array use `CustomFields::validator()->rules(Patient::class, complete: true)`, but keep
the write path, because that array does not reject unknown slugs, inactive fields or retired
option keys.

`null` deletes the stored row. On a `multiselect` an empty array is a stored answer and keeps
the row. Write a `date` as `Y-m-d` and a `datetime` as `Y-m-d H:i:s`, they read back as written.

## Build a form

The package renders nothing. Build the input list from the type registry and the definitions:

```php
$fieldModel = CustomFields::fieldModel();

$fields = $fieldModel::query()
    ->where('entity_type', CustomFields::entityKey(Patient::class))
    ->where('is_active', true)
    ->orderBy('sort_order')
    ->get()
    ->map(fn ($field) => [
        'name' => $field->slug,
        'label' => $field->name,
        'required' => $field->is_required,
        'input' => $field->fieldType()->inputHint(),
        'options' => $field->optionsForInput(),
        'rules' => $field->fieldType()->rules($field),
    ]);
```

For the admin screen that creates definitions, `CustomFields::types()` returns every
registered type keyed by its key, each exposing `label()` and `inputHint()`. There is no
Filament or Livewire integration in the package: map these arrays onto your own components.

## Add a custom field type

1. Implement `PlinCode\CustomFields\Contracts\FieldType`, or extend a built in type such as
   `PlinCode\CustomFields\Types\TextType` and override only what differs. The contract:

```php
public static function key(): string;
public function storageColumn(): string;
public function serialize(mixed $value, Model $field): mixed;
public function deserialize(mixed $value, Model $field): mixed;
public function rules(Model $field): array;
public function default(): mixed;
public function label(): string;
public function inputHint(): string;
public function queryOperations(): array;
```

2. Pick `storageColumn()` from the eight existing columns. The values table has no other
   column, so a new name breaks every write.
3. `serialize()` turns input into the stored shape and is also applied to filter values, so
   filtering compares the same thing a write stores. `deserialize()` turns the stored shape
   into what reads return, and may differ from what `rules()` accepts.
4. Declare in `queryOperations()` only operations that work on the chosen column, using
   `equals`, `in`, `contains`, `greater_than`, `less_than`, `between`, `is_null`, `is_not_null`,
   `contains_any`, `contains_all` and `sort`.

```php
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

5. Register it in `boot()` next to the entities with `CustomFields::registerType(CountryType::class)`.
   A key registered twice throws `InvalidArgumentException`, so a built in key cannot be
   replaced, choose a new one. Nothing checks `type` when a definition is created: a key that
   is not registered throws `InvalidArgumentException` from `CustomFields::type()` at the first
   read or write of that field.
6. An operation outside the vocabulary is allowed, but `CustomFields::filtersFor()` skips it
   (`PlinCode\CustomFields\Query\CustomFieldFilter::supports()` returns `false`) and the
   `CustomFieldFilter` constructor refuses it. Serve it with your own
   `Spatie\QueryBuilder\Filters\Filter` registered under the package name:

```php
use Spatie\QueryBuilder\AllowedFilter;

AllowedFilter::custom(CustomFields::filterName($slug, 'within_reach'), new WithinReachFilter($field));
```

## Filter and sort a listing

1. Make sure `spatie/laravel-query-builder` drives the listing. Spread the package output into
   `allowedFilters()` and `allowedSorts()`. Use `CustomFields::queryOptionsFor()` when you need
   both, it reads the definitions once instead of twice; `CustomFields::filtersFor()` and
   `CustomFields::sortsFor()` return one list each.
2. Call them inside the request, never in `register()` or `boot()`.
3. Compose with your own filters and sorts, including the sorts of
   `plin-code/laravel-eloquent-sorts`:

```php
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

$options = CustomFields::queryOptionsFor(Patient::class);

QueryBuilder::for(Patient::class)
    ->allowedFilters('name', ...$options['filters'])
    ->allowedSorts(AllowedSort::field('last_name'), ...$options['sorts'])
    ->paginate();
```

4. Tell the client the names, built from `key_prefix` and the slug. Equality is
   `filter[cf_slug]`, every other operation appends a colon and its name, as in
   `filter[cf_slug:between]`, and sorts are `sort=cf_slug` and `sort=-cf_slug`. Values:
   `in`, `contains_any`, `contains_all` take a comma separated list, `between` exactly two
   values, `greater_than` and `less_than` one value, `is_null` and `is_not_null` take `1` or
   `0`. A `multiselect` has no bare equality filter, so `filter[cf_allergies]=latex` is
   rejected with Spatie's `InvalidFilterQuery`; send `filter[cf_allergies:contains_any]=latex`.
   Records without a value sort last in both directions.
5. Constants for the operation names live on `CustomFieldFilter` (`CustomFieldFilter::BETWEEN`,
   `CustomFieldFilter::CONTAINS_ANY`) and `PlinCode\CustomFields\Query\CustomFieldSorter::SORT`.

Outside a request, use the scope: `Patient::whereCustomField('allergies', 'latex')` matches by
JSON containment on a `multiselect` and by equality elsewhere.

## Replace the package models

To add a global scope (a tenant, for example), a connection or relations, extend
`PlinCode\CustomFields\Models\CustomField` or `PlinCode\CustomFields\Models\CustomFieldValue`
and point `models.custom_field` or `models.custom_field_value` in the config at the subclass.
In application code resolve the classes with `CustomFields::fieldModel()` and
`CustomFields::valueModel()`, so the configured subclass and its scopes always apply.

## React to changes

Listen for `PlinCode\CustomFields\Events\CustomFieldCreated`, `CustomFieldUpdated`,
`CustomFieldDeleted` (property `$field`) and `CustomFieldValueSaved`, `CustomFieldValueDeleted`
(property `$value`). All of them are dispatched after the surrounding transaction commits.
