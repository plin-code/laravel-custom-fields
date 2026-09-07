<?php

declare(strict_types=1);

use PlinCode\CustomFields\CustomFields;

it('resolves the singleton', function () {
    expect(app(CustomFields::class))->toBeInstanceOf(CustomFields::class);
});

it('returns the same instance from the container', function () {
    expect(app(CustomFields::class))->toBe(app(CustomFields::class));
});

it('merges the package config', function () {
    expect(config('laravel-custom-fields.key_type'))->toBe('id')
        ->and(config('laravel-custom-fields.morph_key_type'))->toBe('uuid');
});

it('loads the package translations', function () {
    expect(trans('laravel-custom-fields::messages.placeholder'))->toBe('CustomFields placeholder translation.');
});
