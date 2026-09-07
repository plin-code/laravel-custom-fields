<?php

declare(strict_types=1);

namespace PlinCode\CustomFields;

use Illuminate\Support\ServiceProvider;
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

class CustomFieldsServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/laravel-custom-fields.php', 'laravel-custom-fields');
        $this->validateKeyConfiguration();

        $this->app->singleton(CustomFields::class);

        $this->app->afterResolving(CustomFields::class, function (CustomFields $manager): void {
            foreach ([TextType::class, TextareaType::class, EmailType::class, UrlType::class, PhoneType::class, NumberType::class, DecimalType::class, BooleanType::class, DateType::class, DateTimeType::class, SelectType::class, MultiSelectType::class] as $type) {
                try {
                    $manager->registerType($type);
                } catch (InvalidArgumentException) {
                    // The consumer may register the built-in type eagerly.
                }
            }
        });
    }

    private function validateKeyConfiguration(): void
    {
        foreach (['key_type', 'morph_key_type'] as $key) {
            if (! in_array(config('laravel-custom-fields.'.$key), ['id', 'uuid', 'ulid'], true)) {
                throw new InvalidArgumentException("Unsupported custom fields key type [{$key}].");
            }
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'laravel-custom-fields');

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/laravel-custom-fields.php' => config_path('laravel-custom-fields.php'),
        ], ['laravel-custom-fields', 'laravel-custom-fields-config']);

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/laravel-custom-fields'),
        ], ['laravel-custom-fields', 'laravel-custom-fields-lang']);

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], ['laravel-custom-fields', 'laravel-custom-fields-migrations']);
    }
}
