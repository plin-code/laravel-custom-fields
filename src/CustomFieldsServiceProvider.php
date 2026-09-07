<?php

declare(strict_types=1);

namespace PlinCode\CustomFields;

use InvalidArgumentException;
use PlinCode\CustomFields\Types\BooleanType;
use PlinCode\CustomFields\Types\DateTimeType;
use PlinCode\CustomFields\Types\DateType;
use PlinCode\CustomFields\Types\DecimalType;
use PlinCode\CustomFields\Types\EmailType;
use PlinCode\CustomFields\Types\MultiSelectType;
use PlinCode\CustomFields\Types\NumberType;
use PlinCode\CustomFields\Types\PhoneType;
use PlinCode\CustomFields\Types\SelectType;
use PlinCode\CustomFields\Types\TextareaType;
use PlinCode\CustomFields\Types\TextType;
use PlinCode\CustomFields\Types\UrlType;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class CustomFieldsServiceProvider extends PackageServiceProvider
{
    /**
     * The package name, used as the config key, the translation namespace
     * and the prefix of every publish tag.
     */
    private const string PACKAGE_NAME = 'laravel-custom-fields';

    /**
     * The field types registered on the manager the first time it is resolved.
     */
    private const array BUILT_IN_TYPES = [
        TextType::class,
        TextareaType::class,
        EmailType::class,
        UrlType::class,
        PhoneType::class,
        NumberType::class,
        DecimalType::class,
        BooleanType::class,
        DateType::class,
        DateTimeType::class,
        SelectType::class,
        MultiSelectType::class,
    ];

    public function configurePackage(Package $package): void
    {
        $package
            ->name(self::PACKAGE_NAME)
            ->hasConfigFile(self::PACKAGE_NAME)
            ->hasTranslations()
            ->hasMigrations([
                '2026_01_01_000000_create_custom_fields_table',
                '2026_01_01_000001_create_custom_field_values_table',
            ]);
    }

    /**
     * Keep the package short name equal to the package name.
     *
     * Package tools strips the "laravel-" prefix by default, which would turn the
     * translation namespace into "custom-fields" and the publish tags into
     * "custom-fields-config" and "custom-fields-migrations".
     */
    public function newPackage(): Package
    {
        return new class extends Package
        {
            public function shortName(): string
            {
                return $this->name;
            }
        };
    }

    public function packageRegistered(): void
    {
        $this->validateKeyConfiguration();

        $this->app->singleton(CustomFields::class);

        $this->app->afterResolving(CustomFields::class, function (CustomFields $manager): void {
            foreach (self::BUILT_IN_TYPES as $type) {
                try {
                    $manager->registerType($type);
                } catch (InvalidArgumentException) {
                    // The consumer may register the built-in type eagerly.
                }
            }
        });
    }

    /**
     * Group the config file and the migrations under the umbrella publish tag as well,
     * so "vendor:publish --tag=laravel-custom-fields" keeps publishing everything.
     */
    public function packageBooted(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        foreach ([self::PACKAGE_NAME.'-config', self::PACKAGE_NAME.'-migrations'] as $tag) {
            $this->publishes(static::pathsToPublish(static::class, $tag), self::PACKAGE_NAME);
        }
    }

    /**
     * Load and publish the translations.
     *
     * Package tools expects them under resources/lang, this package keeps them in lang,
     * and the published tag has to stay "laravel-custom-fields-lang".
     */
    protected function bootPackageTranslations(): self
    {
        if (! $this->package->hasTranslations) {
            return $this;
        }

        $packageTranslations = $this->package->basePath('/../lang');

        $this->loadTranslationsFrom($packageTranslations, $this->package->shortName());

        if ($this->app->runningInConsole()) {
            $this->publishes(
                [$packageTranslations => $this->app->langPath('vendor/'.$this->package->shortName())],
                [self::PACKAGE_NAME, self::PACKAGE_NAME.'-lang'],
            );
        }

        return $this;
    }

    private function validateKeyConfiguration(): void
    {
        foreach (['key_type', 'morph_key_type'] as $key) {
            if (! in_array(config(self::PACKAGE_NAME.'.'.$key), ['id', 'uuid', 'ulid'], true)) {
                throw new InvalidArgumentException("Unsupported custom fields key type [{$key}].");
            }
        }
    }
}
