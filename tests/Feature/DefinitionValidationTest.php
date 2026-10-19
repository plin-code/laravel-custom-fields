<?php

declare(strict_types=1);

use PlinCode\CustomFields\Facades\CustomFields;
use PlinCode\CustomFields\Models\CustomField;
use Workbench\App\Models\Article;

beforeEach(function (): void {
    CustomFields::registerEntity(Article::class, 'article');
});

it('rejects an explicit slug that is not a plain identifier', function (string $slug): void {
    expect(fn (): CustomField => articleField(['name' => 'Price', 'slug' => $slug]))
        ->toThrow(InvalidArgumentException::class, "Custom field slug [{$slug}] may only contain lowercase letters, digits, dashes and underscores.");

    expect(CustomField::query()->count())->toBe(0);
})->with([
    'dot' => 'a.b',
    'asterisk' => 'a*b',
    'colon' => 'price:greater_than',
    'uppercase' => 'Price',
    'leading dash' => '-lead',
    'too long' => str_repeat('a', 101),
]);

it('accepts an explicit slug that is a plain identifier', function (string $slug): void {
    expect(articleField(['name' => 'Price', 'slug' => $slug])->slug)->toBe($slug);
})->with([
    'digits and underscore' => 'price_2',
    'dash' => 'risk-level',
    'single character' => 'x',
]);

it('still generates slugs from the name', function (): void {
    expect(articleField(['name' => 'Risk Level'])->slug)->toBe('risk-level');
});

it('refuses to create a field with an unknown type', function (): void {
    expect(fn (): CustomField => articleField(['name' => 'Rank', 'type' => 'nope']))
        ->toThrow(InvalidArgumentException::class, 'Unknown custom field type [nope].');

    expect(CustomField::query()->count())->toBe(0);
});

it('refuses to change a field to an unknown type', function (): void {
    $field = articleField(['name' => 'Rank']);

    expect(fn (): bool => $field->update(['type' => 'nope']))
        ->toThrow(InvalidArgumentException::class, 'Unknown custom field type [nope].');

    expect($field->fresh()?->type)->toBe('text');
});

it('changes a field without values to a registered type', function (): void {
    $field = articleField(['name' => 'Rank']);

    $field->update(['type' => 'number']);

    expect($field->fresh()?->type)->toBe('number');
});
