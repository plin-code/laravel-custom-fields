# Custom field types

The twelve built in types cover most products, and the type registry is open. This page
covers the `FieldType` contract, adding a type of your own, serving a query operation the
package does not know, the events you can listen to, and the AI guidelines the package
ships for Laravel Boost.

## The FieldType contract

A type implements `PlinCode\CustomFields\Contracts\FieldType`:

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

- `key()` is the string stored in the `type` column of a definition.
- `storageColumn()` is one of the eight typed columns of the values table: `value_string`,
  `value_text`, `value_integer`, `value_decimal`, `value_boolean`, `value_date`,
  `value_datetime`, `value_json`. There is no other column, so any other name breaks every
  write.
- `serialize()` turns a validated input into the stored shape. It also runs on request
  filter values and on the value given to `whereCustomField()`, so a filter compares what a
  write stores. The one exception is a type stored in `value_json`: the scope passes its
  value as it is to `whereJsonContains`.
- `deserialize()` turns the stored shape into what a read returns. It may differ from what
  `rules()` accepts: complete validation never replays the input rules over a stored value.
- `rules()` validates a submitted value, and also a request filter value for `equals`,
  `in`, `greater_than`, `less_than` and `between`. Start it with `nullable`.
- `default()` is what a read returns when nothing is stored.
- `label()` and `inputHint()` are metadata for your own admin and forms.
- `queryOperations()` lists the filter operations the type supports, plus `sort` when the
  field can be sorted on. Declare only operations that work on the chosen column.

## Adding a type

Extend one of the built in types, such as `TextType`, rather than implementing `FieldType`
directly. The interface may gain methods in 1.x, and the built in types will carry them, so
a type that extends one keeps working across minor releases. Implement the interface from
scratch only when no built in type fits the storage column you need.

The example below extends `TextType` to change only part of it:

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

Register it from the `boot()` method of a service provider, next to your entities:

```php
use App\CustomFields\CountryType;
use PlinCode\CustomFields\Facades\CustomFields;

CustomFields::registerType(CountryType::class);
```

A definition can then use it:

```php
use App\Models\Patient;
use PlinCode\CustomFields\Facades\CustomFields;
use PlinCode\CustomFields\Models\CustomField;

CustomField::create([
    'entity_type' => CustomFields::entityKey(Patient::class),
    'name' => 'Country of birth',
    'type' => 'country',
]);
```

A key registered twice throws an `InvalidArgumentException`, so a built in key cannot be
replaced: choose a new one. Register the type before any definition that uses it is saved,
since the `type` of a definition is resolved through the registry on create and on update.
`CustomFields::types()` returns every registered type keyed by its key, which is what an
admin screen lists.

## Query operations outside the vocabulary

There is no hook for a custom query operation. The ten filter operations are fixed in
`PlinCode\CustomFields\Query\CustomFieldFilter::operations()`, and
`CustomFields::filtersFor()` silently skips any entry of `queryOperations()` that is not one
of them (`CustomFieldFilter::supports()` returns `false`). The `CustomFieldFilter`
constructor refuses such an operation outright. A type that declares an operation of its own
therefore gets no filter for it.

The only route is to write a `Spatie\QueryBuilder\Filters\Filter` yourself and register it
next to the package filters, under a name built by `CustomFields::filterName()` so it
follows the same convention:

```php
use Illuminate\Database\Eloquent\Builder;
use PlinCode\CustomFields\Models\CustomField;
use Spatie\QueryBuilder\Filters\Filter;

class StartsWithFilter implements Filter
{
    public function __construct(private CustomField $field) {}

    public function __invoke(Builder $query, mixed $value, string $property): void
    {
        $query->whereHas('customFieldValues', fn (Builder $inner) => $inner
            ->where('custom_field_id', $this->field->getKey())
            ->where($this->field->fieldType()->storageColumn(), 'like', $value.'%'));
    }
}
```

```php
use App\Models\Patient;
use PlinCode\CustomFields\Facades\CustomFields;
use PlinCode\CustomFields\Models\CustomField;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

$country = CustomField::query()
    ->where('entity_type', CustomFields::entityKey(Patient::class))
    ->where('slug', 'country-of-birth')
    ->firstOrFail();

$options = CustomFields::queryOptionsFor(Patient::class);

$patients = QueryBuilder::for(Patient::class)
    ->allowedFilters(
        ...$options['filters'],
        AllowedFilter::custom(CustomFields::filterName('country-of-birth', 'starts_with'), new StartsWithFilter($country)),
    )
    ->paginate();
```

That filter is yours to secure: escape the `LIKE` wildcards in `$value` and check its shape,
since the package validation does not run on it.

## Events

Five events are dispatched, all of them after the surrounding transaction commits, since
each implements `Illuminate\Contracts\Events\ShouldDispatchAfterCommit`:

- `PlinCode\CustomFields\Events\CustomFieldCreated`, `CustomFieldUpdated` and
  `CustomFieldDeleted` carry the definition as `$field`. See
  [defining fields](defining-fields.md#events).
- `PlinCode\CustomFields\Events\CustomFieldValueSaved` and `CustomFieldValueDeleted` carry
  the value row as `$value`. See [reading and writing values](values.md#events).

Deleting a host model fires `CustomFieldValueDeleted` for each of its values. Deleting a
definition removes its values at the database level and fires none.

## AI guidelines (Laravel Boost)

This package ships AI guidelines and an agent skill for
[Laravel Boost](https://github.com/laravel/boost), at the paths the Boost documentation gives
package authors:

- `resources/boost/guidelines/core.blade.php`, a short always in context brief on the
  trait, entity registration, field definitions, the value API, the query helpers and the
  config keys.
- `resources/boost/skills/custom-fields-development/SKILL.md`, loaded on demand, covering
  installation, making a model support custom fields, defining fields of every type, adding
  a custom field type, validating values in a controller, building a form, and filtering and
  sorting a listing.

Install Boost 2.5 or higher with `composer require laravel/boost --dev` then
`php artisan boost:install`, selecting this package when Boost asks which third party
guidelines and skills to install. On an application that already has Boost installed,
`php artisan boost:update --discover` offers the newly detected package. A test in the
package suite checks every class, method and config key those documents name against the
code.
