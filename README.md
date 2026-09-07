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

## Installation

You can install the package via Composer:

```bash
composer require plin-code/laravel-custom-fields
```

The package requires PHP 8.4 or newer and supports Laravel 12 and 13. It also uses
Spatie Query Builder and the Plin Code Eloquent sorts adapter for list integrations.

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

Set key_type and morph_key_type in the published configuration before running the
migrations. Each accepts id, uuid, or ulid. The default internal key is id, while
the default key for owning models is uuid. A single installation must use one key
type consistently.

### Publishing the Translations

```bash
php artisan vendor:publish --tag="laravel-custom-fields-lang"
```

## Usage

Register the models that can own custom fields during application boot:

```php
use PlinCode\CustomFields\Facades\CustomFields;

CustomFields::registerEntity(\App\Models\Patient::class, 'patient', 'Patient');
```

Add the trait to the owning model:

```php
use PlinCode\CustomFields\Concerns\HasCustomFields;

class Patient extends Model
{
    use HasCustomFields;
}
```

The built in types include select, date, and boolean. This example defines the
fields commonly used on a user profile:

~~~php
use App\Models\User;
use PlinCode\CustomFields\Facades\CustomFields;
use PlinCode\CustomFields\Models\CustomField;

CustomFields::registerEntity(User::class, 'user', 'User');

CustomField::create([
    'entity_type' => 'user',
    'name' => 'Sesso',
    'type' => 'select',
    'options' => [
        ['key' => 'm', 'label' => 'M', 'is_active' => true],
        ['key' => 'f', 'label' => 'F', 'is_active' => true],
    ],
]);

CustomField::create([
    'entity_type' => 'user',
    'name' => 'Data di nascita',
    'type' => 'date',
]);

CustomField::create([
    'entity_type' => 'user',
    'name' => 'Consenso marketing',
    'type' => 'boolean',
]);
~~~

After creating a user, assign and read the values through the owning model:

~~~php
$user->setCustomFields([
    'sesso' => 'f',
    'data-di-nascita' => '1990-05-12',
    'consenso-marketing' => true,
]);

$user->getCustomFields();
// [
//     'sesso' => 'f',
//     'data-di-nascita' => '1990-05-12',
//     'consenso-marketing' => true,
// ]
~~~

The date field and boolean field are optional by default. Set is_required on a
definition and request complete validation when the product requires the full
profile.

Definitions are created by the product and belong to one registered entity. Names are trimmed and stored in lowercase. The generated slug is stable and is the key used by the application:

```php
$field = CustomField::create([
    'entity_type' => 'patient',
    'name' => '  Risk level  ',
    'type' => 'select',
    'options' => [
        ['key' => 'low', 'label' => 'Low', 'is_active' => true],
        ['key' => 'legacy', 'label' => 'Legacy', 'is_active' => false],
    ],
]);

$patient->setCustomField($field->slug, 'low');
$patient->getCustomField($field->slug);
```

Partial updates validate only submitted values. A product can request complete
validation when it needs every required active field:

    $patient->setCustomFields(['risk-level' => 'low'], complete: true);

Passing null clears a stored value. clearCustomField() removes it explicitly.
Inactive definitions are excluded from ordinary reads, form metadata, filters, and
sorts. Use getCustomFields(includeInactive: true) for an explicit historical read.

Options use stable keys. Labels can change, while inactive options remain readable on existing records and are excluded from the input metadata returned by `optionsForInput()`.
Use updateOptions() to change labels, activate or deactivate options, and append new
keys. Existing keys cannot be removed or reused.

For API lists, expose only the fields the product wants to make available:

```php
QueryBuilder::for(Patient::class)
    ->allowedFilters(...CustomFields::filtersFor(Patient::class))
    ->allowedSorts(...CustomFields::sortsFor(Patient::class));
```

The package is headless. It does not provide controllers, authorization or UI components. The product owns those layers and can iterate over `CustomFields::types()` to build its widget.

Custom types implement PlinCode\\CustomFields\\Contracts\\FieldType. Register a type
from a service provider with CustomFields::registerType(). A type owns its storage
column, validation rules, serialization, deserialization, defaults, labels, and
declared query operations.

## Contributing

Thank you for considering contributing to Laravel Custom Fields! Please review our [contributing guide](.github/CONTRIBUTING.md) to get started.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Daniele Barbaro](https://github.com/plin-code)
- [All Contributors](../../contributors)

## License

Laravel Custom Fields is open-sourced software licensed under the [MIT license](LICENSE.md).
