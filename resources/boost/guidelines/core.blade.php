## Laravel Custom Fields

`plin-code/laravel-custom-fields` lets a product define fields on Eloquent models at
runtime. A definition is a `PlinCode\CustomFields\Models\CustomField` row, and every value
is a `PlinCode\CustomFields\Models\CustomFieldValue` row holding the value in one typed
column (`value_string`, `value_text`, `value_integer`, `value_decimal`, `value_boolean`,
`value_date`, `value_datetime`, `value_json`). The package is headless: no routes, no
controllers, no UI. Use the facade `PlinCode\CustomFields\Facades\CustomFields`.

### Making a model own custom fields

There is no interface to implement. Add the `PlinCode\CustomFields\Concerns\HasCustomFields`
trait and register the model under a short entity key in the `boot()` method of a service
provider, on every request, since the key also goes into the morph map.

@verbatim
<code-snippet name="Register an entity" lang="php">
use App\Models\Patient;
use PlinCode\CustomFields\Facades\CustomFields;

CustomFields::registerEntity(Patient::class, 'patient', 'Patient');
</code-snippet>
@endverbatim

### Defining fields

Fill `entity_type` with `CustomFields::entityKey()`, never a typed string: nothing checks it,
and a typo creates a field every read ignores. `type` is a registered type key: `text`,
`textarea`, `email`, `url`, `phone`, `number`, `decimal`, `boolean`, `date`, `datetime`,
`select`, `multiselect` (not `multi_select`). The slug is generated from the name on
creation and never changes, it is the key used everywhere else.

@verbatim
<code-snippet name="Create a select definition" lang="php">
use PlinCode\CustomFields\Models\CustomField;

$field = CustomField::create([
    'entity_type' => CustomFields::entityKey(Patient::class),
    'name' => 'Risk level',
    'type' => 'select',
    'is_required' => true,
    'options' => [
        ['key' => 'low', 'label' => 'Low', 'is_active' => true],
        ['key' => 'high', 'label' => 'High', 'is_active' => true],
    ],
]);

$field->slug; // 'risk-level'
</code-snippet>
@endverbatim

Option keys are permanent. Change options with `$field->updateOptions()`, deactivate an
option with `is_active => false`, never remove one (it throws).

### Reading and writing values

@verbatim
<code-snippet name="Values on a persisted model" lang="php">
$patient->setCustomFields(['risk-level' => 'high', 'notes' => 'Follow up.']);
$patient->setCustomFields($payload, complete: true);
$patient->setCustomField('notes', null); // deletes the stored row
$patient->getCustomField('risk-level');   // 'high'
$patient->getCustomFields();              // every active field, keyed by slug
$patient->clearCustomField('notes');
</code-snippet>
@endverbatim

- Writes validate the whole batch, then write it in one transaction. Partial validation
  (the default) checks only the submitted keys; `complete: true` also enforces required
  fields against the stored values.
- An invalid value, an unknown slug or an inactive field throws
  `Illuminate\Validation\ValidationException` keyed by slug.
- Writing to an unsaved model throws `PlinCode\CustomFields\Exceptions\ModelNotPersistedException`.
- `getCustomField()`, `clearCustomField()` and the `whereCustomField()` scope throw
  `PlinCode\CustomFields\Exceptions\UnknownCustomFieldException` for an unknown slug.
- Validate without writing with `CustomFields::validate($patient, $values, complete: true)`.

### Querying

`Patient::whereCustomField('risk-level', 'high')` is an equality scope on the stored shape.
Request filters and sorts are built for `spatie/laravel-query-builder`, one per operation the
field type declares. They read the definitions from the database, so call them while serving
a request, never in a service provider.

@verbatim
<code-snippet name="Expose custom fields to a request" lang="php">
use Spatie\QueryBuilder\QueryBuilder;

$options = CustomFields::queryOptionsFor(Patient::class);

QueryBuilder::for(Patient::class)
    ->allowedFilters(...$options['filters'])
    ->allowedSorts(...$options['sorts'])
    ->paginate();
</code-snippet>
@endverbatim

Names are the prefix plus the slug, with `:operation` for anything but equality:
`filter[cf_risk-level]=high`, `filter[cf_risk-level:in]=high,low`, `sort=-cf_risk-level`.
Build them with `CustomFields::filterName()` and `CustomFields::sortName()`.

### Configuration

Read `config/laravel-custom-fields.php` before assuming table names or key types:

- `laravel-custom-fields.tables.fields` and `laravel-custom-fields.tables.values`: table names.
- `laravel-custom-fields.key_type` and `laravel-custom-fields.morph_key_type`: `id`, `uuid` or
  `ulid`. Set them before running the migrations.
- `laravel-custom-fields.models.custom_field` and `laravel-custom-fields.models.custom_field_value`:
  replacement models, which must extend the package ones. Resolve them with
  `CustomFields::fieldModel()` and `CustomFields::valueModel()`.
- `laravel-custom-fields.key_prefix`: the filter and sort prefix, `cf_` by default.
