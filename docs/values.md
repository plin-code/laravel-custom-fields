# Reading and writing values

A value is a `PlinCode\CustomFields\Models\CustomFieldValue` row: one row per field per
record, holding the value in the typed column of its field. This page covers the value API
on the `HasCustomFields` trait, partial and complete validation, what each type accepts,
the exceptions, building a form, and what happens to the values when the record is deleted.
The examples reuse the four definitions from [defining fields](defining-fields.md#creating-a-definition).

## The value API

The trait adds these methods to the model:

- `getCustomField(string $slug, bool $includeInactive = false): mixed`
- `getCustomFields(bool $includeInactive = false): array`
- `setCustomField(string $slug, mixed $value): void`
- `setCustomFields(array $values, bool $complete = false): void`
- `clearCustomField(string $slug): void`
- `customFieldValues(): MorphMany`

```php
$patient->setCustomFields([
    'risk-level' => 'high',
    'date-of-birth' => '1985-03-02',
    'allergies' => ['pollen', 'latex'],
]);

$patient->getCustomFields();
// [
//     'allergies' => ['pollen', 'latex'],
//     'date-of-birth' => '1985-03-02',
//     'notes' => null,
//     'risk-level' => 'high',
// ]

$patient->getCustomField('risk-level'); // 'high'
$patient->setCustomField('notes', 'Follow up in June.');
$patient->clearCustomField('notes');
```

`getCustomFields()` returns every active field of the entity keyed by slug, including the
fields with no stored value, so the array shape is stable and safe to hand to a form. The
keys are ordered by `sort_order` and then by slug, which is why `risk-level`, the only field
with a `sort_order` of 10, comes last.

`setCustomFields()` validates the whole batch first and then writes it inside a single
transaction, so a rejected batch leaves nothing behind. `setCustomField()` is a single key
call onto the same path. Both refuse a model that has not been saved yet and throw a
`ModelNotPersistedException` without issuing a query.

## Clearing a value

Passing `null` deletes the stored row. Reading the field afterwards returns the default of
its type, and a `CustomFieldValueDeleted` event is dispatched.

```php
$patient->setCustomField('date-of-birth', null); // the row is deleted
$patient->getCustomField('date-of-birth');       // null

$patient->clearCustomField('date-of-birth');     // the same thing, explicitly
```

An empty string means `null` on every type: `setCustomField('notes', '')` deletes the row
too. A string made only of whitespace is not empty and is stored as it is.

An empty array is not the same as `null` on a `multiselect`. An empty array is an answered
field, so the row is kept and the value reads back as `[]`. Only `null` deletes the row.

```php
$patient->setCustomField('allergies', []);   // row kept, reads back as []
$patient->setCustomField('allergies', null); // row deleted, reads back as []
```

## Partial and complete validation

The package validates in two modes.

**Partial** is the default. Only the submitted keys are inspected, so a required field that
is absent from the payload is not checked. A required field explicitly submitted as `null`
or `''` is cleared with no error, since clearing is what `null` means. This is what a PATCH
endpoint wants.

**Complete** inspects the state the record ends up with, the values already stored merged
with the changes you submit. A required field that is already stored may be left out of the
payload, while a required field that is explicitly submitted as `null` or `''`, or that was
never stored at all, fails. A required `multiselect` needs at least one option selected.

```php
$patient->setCustomFields(['risk-level' => null]);                 // partial: risk-level is cleared
$patient->setCustomFields(['risk-level' => null], complete: true); // complete: ValidationException
```

You can validate without writing, which is useful in a form request:

```php
use PlinCode\CustomFields\Facades\CustomFields;

CustomFields::validate($patient, $request->input('custom_fields', []), complete: true);
```

Or take the rule array and merge it into your own rules:

```php
$rules = CustomFields::validator()->rules(Patient::class, complete: true);
// ['allergies' => ['nullable', 'array'], ..., 'risk-level' => ['required', 'string', Rule::in([...])]]
```

> [!WARNING]
> The rule array is not equivalent to `CustomFields::validate()`. It carries the rules of
> each type and nothing else, so it does not reject a slug that is unknown or inactive, it
> accepts an inactive option key on a `select`, and it does not check the elements of a
> `multiselect` at all. `validate()` performs all of those checks on top of the rules.
> Use the array to drive a form request, then let the write path validate again, which it
> always does.

Messages come from the `laravel-custom-fields` translation namespace, in English and
Italian, except the required message, which is Laravel's own `validation.required` so it
follows your application locale. Both use the field name rather than the slug as the
attribute, so a field named `date of birth` produces "The date of birth field is required."
and not "The date-of-birth field is required."

## Validation rules per type

Validation runs in two layers. The first is what the type's `rules()` returns, which is
also what `CustomFields::validator()->rules()` hands you. The second is what the validator
adds on top when it validates a payload.

| Key | `rules()` returns | The validator adds |
| --- | --- | --- |
| `text`, `phone` | `nullable`, `string`, `max:255` | |
| `textarea` | `nullable`, `string` | |
| `email` | `nullable`, `email`, `max:255` | |
| `url` | `nullable`, `url`, `max:255` | |
| `number` | `nullable`, `integer` | |
| `decimal` | `nullable`, `numeric`, `decimal:0,6`, `between:-99999999999999.999999,99999999999999.999999` | |
| `boolean` | `nullable`, `boolean` | |
| `date`, `datetime` | `nullable`, `date` | |
| `select` | `nullable`, `string`, `Illuminate\Validation\Rule::in()` over every option key, inactive ones included | The key has to be active, unless it is the value the record already holds. |
| `multiselect` | `nullable`, `array` | Every element has to be an option key, and an active one unless the record already holds it. |

For every type the validator also rejects an unknown slug and an inactive field, and under
complete validation it checks every required field as described above. The element check of
a `multiselect` and the active check of a `select` live in
`PlinCode\CustomFields\Validation\ValueValidator::validateOptions()`.

A value that passes is normalised before it is stored:

- `date` is stored and read as `Y-m-d`. Anything the `date` rule accepts is normalised:
  `15 January 2024`, `01/15/2024` (read as month/day/year), ISO 8601 strings and Carbon
  instances. A date is never shifted between timezones.
- `datetime` is stored and read as `Y-m-d H:i:s` in the application timezone. A naive string
  is read in that timezone, and a value with an offset or `Z` is converted to it.
- `decimal` accepts at most six fraction digits within `±99999999999999.999999`, with no
  exponent notation and no surrounding whitespace. A leading `+` is stripped. It reads back
  as a string with the trailing zeros trimmed, so `12.50` reads back as `'12.5'` on every
  driver.
- `number` is cast to an integer and `boolean` to a boolean.
- `multiselect` is stored as a JSON array of keys.

## Exceptions

| Exception | Thrown by | When | Reasonable response |
| --- | --- | --- | --- |
| `Illuminate\Validation\ValidationException` | `setCustomField()`, `setCustomFields()`, `CustomFields::validate()` | A value fails the rules of its type, a required field is missing under complete validation, a submitted slug is unknown, a submitted definition is inactive, or an option key cannot be assigned. Errors are keyed by slug. | 422 |
| `PlinCode\CustomFields\Exceptions\ModelNotPersistedException` | `setCustomField()`, `setCustomFields()` | The host model has no key yet. | 500, it is a programming error |
| `PlinCode\CustomFields\Exceptions\UnknownCustomFieldException` | `getCustomField()`, `clearCustomField()`, `whereCustomField()` | The slug is not defined for the entity. Also when it is inactive, except for `clearCustomField()`, which always works, and for `getCustomField($slug, includeInactive: true)`. `whereCustomField()` has no opt in. | 404 or 500, depending on whether the slug came from a request |
| `InvalidArgumentException` | Every call that resolves the entity: the trait methods, `whereCustomField()`, `CustomFields::entityKey()`, `CustomFields::filtersFor()`, `CustomFields::sortsFor()`, `CustomFields::queryOptionsFor()`, `CustomFields::validate()`, `CustomFields::validator()->rules()` | The model is not registered with `registerEntity()`. | 500 |
| `InvalidArgumentException` | `CustomFields::registerEntity()` | The key is taken by another model, the model is registered under another key, or the morph map assigns the key to another class. | 500 |
| `InvalidArgumentException` | `CustomFields::registerType()`, `CustomFields::type()` | A type key registered twice, or an unknown type key. | 500 |
| `InvalidArgumentException` | `CustomField::create()`, `update()`, `save()`, `updateOptions()` | An empty name, an explicit slug that breaks the slug rule, an unknown type, an option without a key or a label, a duplicated option key, a removed option, a slug or entity change, or a type change while values exist. | 422 if the input came from a request and you catch it, otherwise 500 |
| `InvalidArgumentException` | The `CustomFieldFilter` and `CustomFieldSorter` constructors | An operation outside the ten filter operations, an operation the type does not declare, or a sort on a type that does not declare `sort`. | 500 |
| `InvalidArgumentException` | The service provider | `key_type` or `morph_key_type` is not `id`, `uuid` or `ulid`. | The application fails at boot |
| `Illuminate\Database\UniqueConstraintViolationException` | `CustomField::create()`, `update()` | Two definitions of the same entity are given the same name, on create or by renaming one, or an explicit slug that is already taken. The uniqueness is enforced by the database. | 409 or 422, after you catch it |
| `PlinCode\CustomFields\Exceptions\InvalidFilterValueException` | Request filters | A filter value fails the rules of the field type, a list holds an empty element, or the value has the wrong count for its operation. A filter with no value at all is ignored under Laravel's default middleware. | 400, see [invalid filter values](querying.md#invalid-filter-values) |

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
    ->orderBy('slug')
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

## The relation and eager loading

`customFieldValues()` is a `MorphMany` to the configured value model, through the
`valuable` pair. `getCustomField()` and `getCustomFields()` always query it themselves, two
queries per call (the definitions and the rows), and do not read an eager loaded relation.
To show values for a whole list without a query per record, eager load the rows with their
definitions and read them yourself:

```php
$patients = Patient::with('customFieldValues.customField')->get();

$values = $patients->first()->customFieldValues
    ->filter(fn ($row) => $row->customField->is_active)
    ->mapWithKeys(fn ($row) => [$row->customField->slug => $row->getValue()]);
```

`getValue()` applies the same deserialization as `getCustomFields()`. A field with no row is
simply absent from that collection. `setCustomFields()` unsets the loaded relation after a
write, so a stale relation is never left on the model.

## Deleting the record

Deleting a model that uses the trait deletes its values. The rows are removed one by one
through Eloquent, so `CustomFieldValueDeleted` fires for each, exactly as
`clearCustomField()` does. There is no foreign key on the polymorphic pair, so this hook is
the only thing that removes them.

- A model that uses `SoftDeletes` keeps its values when it is soft deleted, because it can
  still be restored. They are removed when it is force deleted.
- A bulk delete through the query builder, such as `Patient::where('archived', true)->delete()`,
  fires no model events and leaves the value rows behind. Delete those records one by one,
  or remove their rows yourself.

## Events

A value dispatches two events, after the surrounding transaction commits:

| Event | Property | Dispatched when |
| --- | --- | --- |
| `PlinCode\CustomFields\Events\CustomFieldValueSaved` | `$value` | A value row is inserted or updated. |
| `PlinCode\CustomFields\Events\CustomFieldValueDeleted` | `$value` | A value row is removed through Eloquent: by `clearCustomField()`, by a `null` or `''` write, by deleting the host model, or by deleting the row yourself. |

`$value` is a public readonly `CustomFieldValue`. Deleting a definition removes its values
at the database level and fires no `CustomFieldValueDeleted`, see
[deleting a field](defining-fields.md#deleting-a-field). Use these events to invalidate a
cache, to reindex a search document, or to write an audit trail.

## Factories

`PlinCode\CustomFields\Database\Factories\CustomFieldValueFactory` builds a raw value row.
It leaves `custom_field_id` empty and points at an `article` with key `1`, so pass the
field, the owner and the typed column yourself:

```php
use PlinCode\CustomFields\Database\Factories\CustomFieldValueFactory;

CustomFieldValueFactory::new()->create([
    'custom_field_id' => $riskLevel->getKey(),
    'valuable_type' => $patient->getMorphClass(),
    'valuable_id' => $patient->getKey(),
    'value_string' => 'high',
]);
```

The factory writes the column as given, without validation or normalisation. In most tests
`setCustomFields()` is the better tool.
