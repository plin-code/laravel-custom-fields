<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use PlinCode\CustomFields\Facades\CustomFields;
use PlinCode\CustomFields\Models\CustomField;
use PlinCode\CustomFields\Query\CustomFieldFilter;
use Workbench\App\CustomFields\RatingType;
use Workbench\App\Models\Article;

beforeEach(function (): void {
    CustomFields::registerEntity(Article::class, 'article');
});

/**
 * @param  array<int, array{key: string, label: string, is_active: bool}>|null  $options
 */
function typedField(string $type, ?array $options = null): CustomField
{
    return CustomField::create([
        'entity_type' => 'article',
        'name' => $type.' field',
        'type' => $type,
        'options' => $options,
    ]);
}

$options = [
    ['key' => 'alpha', 'label' => 'Alpha', 'is_active' => true],
    ['key' => 'beta', 'label' => 'Beta', 'is_active' => true],
];

it('round trips a value of every built in type', function (string $type, ?array $options, mixed $input, mixed $expected, string $column): void {
    $field = typedField($type, $options);
    $article = Article::create(['title' => 'A']);

    $article->setCustomField($field->slug, $input);
    $row = $article->customFieldValues()->first();

    expect($field->fieldType()->storageColumn())->toBe($column)
        ->and($row?->getAttribute($column))->not->toBeNull()
        ->and($article->getCustomField($field->slug))->toBe($expected);
})->with([
    'text' => ['text', null, 'Sector', 'Sector', 'value_string'],
    'textarea' => ['textarea', null, str_repeat('long ', 200), str_repeat('long ', 200), 'value_text'],
    'email' => ['email', null, 'person@example.com', 'person@example.com', 'value_string'],
    'url' => ['url', null, 'https://example.com/a?b=c', 'https://example.com/a?b=c', 'value_string'],
    'phone' => ['phone', null, '+39 02 1234 5678', '+39 02 1234 5678', 'value_string'],
    'number' => ['number', null, 42, 42, 'value_integer'],
    'decimal' => ['decimal', null, '1234.567891', '1234.567891', 'value_decimal'],
    'boolean' => ['boolean', null, true, true, 'value_boolean'],
    'date' => ['date', null, '2024-01-31', '2024-01-31', 'value_date'],
    'datetime' => ['datetime', null, '2024-01-31 12:30:45', '2024-01-31 12:30:45', 'value_datetime'],
    'select' => ['select', $options, 'alpha', 'alpha', 'value_string'],
    'multiselect' => ['multiselect', $options, ['alpha', 'beta'], ['alpha', 'beta'], 'value_json'],
]);

it('spreads the twelve built in types over the eight storage columns', function (): void {
    $columns = array_map(
        static fn (object $type): string => $type->storageColumn(),
        CustomFields::types(),
    );

    expect($columns)->toHaveCount(12)
        ->and(array_values(array_unique($columns)))->toEqualCanonicalizing([
            'value_string',
            'value_text',
            'value_integer',
            'value_decimal',
            'value_boolean',
            'value_date',
            'value_datetime',
            'value_json',
        ]);
});

it('keeps the scale of a decimal value', function (): void {
    $field = typedField('decimal');
    $article = Article::create(['title' => 'A']);

    $article->setCustomField($field->slug, 12.5);

    expect($article->getCustomField($field->slug))->toBe('12.5');
});

it('keeps a value at the top of the bigint range', function (): void {
    $field = typedField('number');
    $article = Article::create(['title' => 'A']);

    $article->setCustomField($field->slug, PHP_INT_MAX);

    expect($article->getCustomField($field->slug))->toBe(PHP_INT_MAX);
});

it('reads a boolean false as a value rather than as an absence', function (): void {
    $field = typedField('boolean');
    $article = Article::create(['title' => 'A']);

    $article->setCustomField($field->slug, false);

    expect($article->getCustomField($field->slug))->toBeFalse()
        ->and($article->customFieldValues()->count())->toBe(1);
});

it('returns the default of the type when nothing is stored', function (): void {
    typedField('number');
    typedField('multiselect', [['key' => 'alpha', 'label' => 'Alpha', 'is_active' => true]]);
    $article = Article::create(['title' => 'A']);

    expect($article->getCustomFields())->toBe([
        'number-field' => null,
        'multiselect-field' => [],
    ]);
});

it('holds a text value to the length its column can take', function (): void {
    $text = typedField('text');
    $textarea = typedField('textarea');
    $article = Article::create(['title' => 'A']);

    $article->setCustomField($textarea->slug, str_repeat('a', 300));

    expect($article->getCustomField($textarea->slug))->toHaveLength(300)
        ->and(fn () => $article->setCustomField($text->slug, str_repeat('a', 256)))
        ->toThrow(ValidationException::class);
});

it('accepts a field type a consumer registers on its own', function (): void {
    CustomFields::registerType(RatingType::class);
    $field = typedField('rating');
    $article = Article::create(['title' => 'A']);

    $article->setCustomField($field->slug, 4);

    expect($field->fieldType())->toBeInstanceOf(RatingType::class)
        ->and($article->customFieldValues()->first()?->getAttribute('value_integer'))->toBe(4)
        ->and($article->getCustomField($field->slug))->toBe('4 stars')
        ->and(Article::create(['title' => 'B'])->getCustomField($field->slug))->toBe('0 stars')
        ->and(fn () => $article->setCustomField($field->slug, 9))->toThrow(ValidationException::class);
});

it('exposes only the operations of a consumer type the core can serve', function (): void {
    CustomFields::registerType(RatingType::class);
    typedField('rating');

    $names = array_map(
        static fn (object $filter): string => (string) $filter->getName(),
        CustomFields::filtersFor(Article::class),
    );

    expect($names)->toBe(['cf_rating-field', 'cf_rating-field:between'])
        ->and(CustomFields::filterName('rating-field', RatingType::WITHIN_REACH))->toBe('cf_rating-field:within_reach')
        ->and(CustomFieldFilter::supports(RatingType::WITHIN_REACH))->toBeFalse();
});

it('refuses to register the same type key twice', function (): void {
    CustomFields::registerType(RatingType::class);

    expect(fn () => CustomFields::registerType(RatingType::class))
        ->toThrow(InvalidArgumentException::class, 'Custom field type [rating] is already registered.');
});

it('refuses to resolve a type that was never registered', function (): void {
    expect(fn (): object => CustomFields::type('rating'))
        ->toThrow(InvalidArgumentException::class, 'Unknown custom field type [rating].');
});
