<?php

declare(strict_types=1);

namespace PlinCode\CustomFields;

use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use PlinCode\CustomFields\Types\MultiSelectType;
use PlinCode\CustomFields\Types\NumberType;
use PlinCode\CustomFields\Types\SelectType;
use PlinCode\CustomFields\Types\TextType;

class CustomFieldsServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/laravel-custom-fields.php', 'laravel-custom-fields');

        $this->app->singleton(CustomFields::class);

        $this->app->afterResolving(CustomFields::class, function (CustomFields $manager): void {
            foreach ([TextType::class, NumberType::class, SelectType::class, MultiSelectType::class] as $type) {
                try {
                    $manager->registerType($type);
                } catch (InvalidArgumentException) {
                    // The consumer may register the built-in type eagerly.
                }
            }
        });
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
