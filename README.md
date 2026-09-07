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

### Publishing the Translations

```bash
php artisan vendor:publish --tag="laravel-custom-fields-lang"
```

## Usage

<!-- Add a basic usage example here. -->

## Contributing

Thank you for considering contributing to Laravel Custom Fields! Please review our [contributing guide](.github/CONTRIBUTING.md) to get started.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Daniele Barbaro](https://github.com/plin-code)
- [All Contributors](../../contributors)

## License

Laravel Custom Fields is open-sourced software licensed under the [MIT license](LICENSE.md).
