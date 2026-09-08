<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use PlinCode\CustomFields\CustomFields;
use PlinCode\CustomFields\CustomFieldsServiceProvider;
use PlinCode\CustomFields\Facades\CustomFields as CustomFieldsFacade;
use PlinCode\CustomFields\Types\TextType;

/**
 * Separators are normalised so the expectations read the same on Windows.
 *
 * Package tools joins its base path to a directory that already starts with a
 * separator, so a published path arrives as src\/../config on Windows. Both the
 * backslashes and the doubled separator have to go.
 */
function normalisePath(string $path): string
{
    return (string) preg_replace('#/+#', '/', str_replace('\\', '/', $path));
}

/** @return array<int, string> */
function publishedPaths(string $tag): array
{
    $root = normalisePath(dirname(__DIR__, 2)).'/';

    return array_map(
        static fn (string $path): string => str_replace([$root, 'src/../'], '', normalisePath($path)),
        array_keys(ServiceProvider::pathsToPublish(CustomFieldsServiceProvider::class, $tag)),
    );
}

/** @return array<int, string> */
function publishedTargets(string $tag): array
{
    return array_map(
        'normalisePath',
        array_values(ServiceProvider::pathsToPublish(CustomFieldsServiceProvider::class, $tag)),
    );
}

it('resolves the singleton', function (): void {
    expect(app(CustomFields::class))->toBeInstanceOf(CustomFields::class);
});

it('returns the same instance from the container', function (): void {
    expect(app(CustomFields::class))->toBe(app(CustomFields::class));
});

it('merges the package config', function (): void {
    expect(config('laravel-custom-fields.key_type'))->toBe('id')
        ->and(config('laravel-custom-fields.morph_key_type'))->toBe('id')
        ->and(config('laravel-custom-fields.key_prefix'))->toBe('cf_')
        ->and(config('laravel-custom-fields.tables.fields'))->toBe('custom_fields')
        ->and(config('laravel-custom-fields.tables.values'))->toBe('custom_field_values');
});

it('registers the publish tags of the package', function (): void {
    expect(array_values(array_filter(
        array_keys(ServiceProvider::$publishGroups),
        static fn (string $tag): bool => str_starts_with($tag, 'laravel-custom-fields'),
    )))->toEqualCanonicalizing([
        'laravel-custom-fields',
        'laravel-custom-fields-config',
        'laravel-custom-fields-lang',
        'laravel-custom-fields-migrations',
    ]);
});

it('publishes only the config file under the config tag', function (): void {
    expect(publishedPaths('laravel-custom-fields-config'))
        ->toBe(['config/laravel-custom-fields.php']);
});

it('publishes only the translations under the lang tag', function (): void {
    expect(publishedPaths('laravel-custom-fields-lang'))->toBe(['lang'])
        ->and(publishedTargets('laravel-custom-fields-lang'))
        ->each->toEndWith('lang/vendor/laravel-custom-fields');
});

it('publishes both migrations under the migrations tag', function (): void {
    expect(publishedPaths('laravel-custom-fields-migrations'))->toBe([
        'database/migrations/2026_01_01_000000_create_custom_fields_table.php',
        'database/migrations/2026_01_01_000001_create_custom_field_values_table.php',
    ]);
});

it('publishes config, translations and migrations under the umbrella tag', function (): void {
    expect(publishedPaths('laravel-custom-fields'))->toEqualCanonicalizing([
        'config/laravel-custom-fields.php',
        'lang',
        'database/migrations/2026_01_01_000000_create_custom_fields_table.php',
        'database/migrations/2026_01_01_000001_create_custom_field_values_table.php',
    ]);
});

it('ships the migrations under the name of the tables they create', function (): void {
    $files = array_map('basename', (array) glob(__DIR__.'/../../database/migrations/*.php'));

    expect($files)->toBe([
        '2026_01_01_000000_create_custom_fields_table.php',
        '2026_01_01_000001_create_custom_field_values_table.php',
    ]);
});

it('registers the twelve built in field types', function (): void {
    expect(array_keys(CustomFieldsFacade::types()))->toEqualCanonicalizing([
        'text',
        'textarea',
        'email',
        'url',
        'phone',
        'number',
        'decimal',
        'boolean',
        'date',
        'datetime',
        'select',
        'multiselect',
    ]);
});

it('tolerates a built in type the consumer registered first', function (): void {
    $manager = new CustomFields;
    $manager->registerType(TextType::class);

    app()->instance(CustomFields::class, $manager);

    expect(fn (): array => app(CustomFields::class)->types())->not->toThrow(InvalidArgumentException::class);
});

it('rejects an unsupported key type while registering', function (): void {
    config()->set('laravel-custom-fields.key_type', 'snowflake');

    expect(fn (): mixed => (new CustomFieldsServiceProvider(app()))->register())
        ->toThrow(InvalidArgumentException::class, 'Unsupported custom fields key type [key_type].');
});

it('rejects an unsupported morph key type while registering', function (): void {
    config()->set('laravel-custom-fields.morph_key_type', 'snowflake');

    expect(fn (): mixed => (new CustomFieldsServiceProvider(app()))->register())
        ->toThrow(InvalidArgumentException::class, 'Unsupported custom fields key type [morph_key_type].');
});

it('loads the package translations in english', function (): void {
    app()->setLocale('en');

    expect(trans('laravel-custom-fields::messages.validation.invalid_option', ['option' => 'legacy', 'attribute' => 'status']))
        ->toBe('The selected option legacy is invalid for status.')
        ->and(trans('laravel-custom-fields::messages.validation.unknown_field', ['attribute' => 'rank']))
        ->toBe('The rank field is not defined for this model.')
        ->and(trans('laravel-custom-fields::messages.validation.inactive_field', ['attribute' => 'rank']))
        ->toBe('The rank field is not active.');
});

it('loads the package translations in italian', function (): void {
    app()->setLocale('it');

    expect(trans('laravel-custom-fields::messages.validation.invalid_option', ['option' => 'legacy', 'attribute' => 'status']))
        ->toBe('L’opzione selezionata legacy non è valida per status.')
        ->and(trans('laravel-custom-fields::messages.validation.unknown_field', ['attribute' => 'rank']))
        ->toBe('Il campo rank non è definito per questo modello.')
        ->and(trans('laravel-custom-fields::messages.validation.inactive_field', ['attribute' => 'rank']))
        ->toBe('Il campo rank non è attivo.');
});

it('keeps the facade annotations in step with the manager', function (): void {
    $reflection = new ReflectionClass(CustomFieldsFacade::class);
    preg_match_all('/@method static [^ ]+(?:<[^>]*>|\{[^}]*\})? (\w+)\(/', (string) $reflection->getDocComment(), $matches);

    $documented = $matches[1];
    $public = array_values(array_map(
        static fn (ReflectionMethod $method): string => $method->getName(),
        array_filter(
            (new ReflectionClass(CustomFields::class))->getMethods(ReflectionMethod::IS_PUBLIC),
            static fn (ReflectionMethod $method): bool => ! $method->isStatic() && ! $method->isConstructor(),
        ),
    ));

    expect($documented)->toEqualCanonicalizing($public);
});
