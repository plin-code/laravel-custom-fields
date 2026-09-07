<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Validation\ValidationException;
use PlinCode\CustomFields\Facades\CustomFields;
use PlinCode\CustomFields\Models\CustomField;
use PlinCode\CustomFields\Query\CustomFieldFilter;
use PlinCode\CustomFields\Query\CustomFieldSorter;
use Workbench\App\Models\Article;

beforeEach(function (): void {
    CustomFields::registerEntity(Article::class, 'article');
});

it('creates a normalized field with a stable slug', function (): void {
    $field = CustomField::create([
        'entity_type' => 'article',
        'name' => '  Sector  ',
        'type' => 'text',
    ]);

    expect($field->name)->toBe('sector')
        ->and($field->slug)->toBe('sector');
});

it('registers the entity in the Laravel morph map', function (): void {
    expect(Relation::getMorphedModel('article'))->toBe(Article::class);
});

it('uses configured string keys for field models', function (): void {
    config()->set('laravel-custom-fields.key_type', 'ulid');

    $field = new CustomField;

    expect($field->getKeyType())->toBe('string')
        ->and($field->getIncrementing())->toBeFalse();
});

it('writes and reads a typed custom value', function (): void {
    $field = CustomField::create([
        'entity_type' => 'article',
        'name' => 'Rank',
        'type' => 'number',
    ]);
    $article = Article::create(['title' => 'A']);

    $article->setCustomField($field->slug, 10);

    expect($article->getCustomField($field->slug))->toBe(10);
});

it('keeps fields isolated by entity type', function (): void {
    CustomFields::registerEntity(Article::class, 'another-article');

    $field = CustomField::create([
        'entity_type' => 'missing',
        'name' => 'Unknown',
        'type' => 'text',
    ]);

    expect($field->entity_type)->toBe('missing');
});

it('validates a custom value before writing it', function (): void {
    $field = CustomField::create([
        'entity_type' => 'article',
        'name' => 'Rank',
        'type' => 'number',
    ]);
    $article = Article::create(['title' => 'A']);

    expect(fn () => $article->setCustomField($field->slug, 'invalid'))
        ->toThrow(ValidationException::class);
});

it('keeps an inactive selected option readable but blocks new assignments', function (): void {
    $field = CustomField::create([
        'entity_type' => 'article',
        'name' => 'Status',
        'type' => 'select',
        'options' => [
            ['key' => 'legacy', 'label' => 'Legacy', 'is_active' => true],
            ['key' => 'current', 'label' => 'Current', 'is_active' => true],
        ],
    ]);
    $article = Article::create(['title' => 'A']);
    $article->setCustomField($field->slug, 'legacy');

    $field->update(['options' => [
        ['key' => 'legacy', 'label' => 'Legacy', 'is_active' => false],
        ['key' => 'current', 'label' => 'Current', 'is_active' => true],
    ]]);

    expect($article->getCustomField($field->slug))->toBe('legacy')
        ->and(fn () => Article::create(['title' => 'B'])->setCustomField($field->slug, 'legacy'))
        ->toThrow(ValidationException::class);
});

it('keeps option keys stable while allowing labels to change', function (): void {
    $field = CustomField::create([
        'entity_type' => 'article',
        'name' => 'Sector',
        'type' => 'select',
        'options' => [
            ['key' => 'retail', 'label' => 'Retail', 'is_active' => true],
        ],
    ]);

    $field->updateOptions([
        ['key' => 'retail', 'label' => 'Vendita', 'is_active' => true],
        ['key' => 'public', 'label' => 'Public', 'is_active' => true],
    ]);

    expect($field->refresh()->options[0]['label'])->toBe('Vendita')
        ->and(fn () => $field->updateOptions([
            ['key' => 'public', 'label' => 'Public', 'is_active' => true],
        ]))->toThrow(InvalidArgumentException::class);
});

it('protects the stable slug from ordinary updates', function (): void {
    $field = CustomField::create([
        'entity_type' => 'article',
        'name' => 'Sector',
        'type' => 'text',
    ]);

    expect(fn () => $field->update(['slug' => 'changed']))
        ->toThrow(InvalidArgumentException::class);
});

it('protects the field type when values already exist', function (): void {
    $field = CustomField::create([
        'entity_type' => 'article',
        'name' => 'Rank',
        'type' => 'number',
    ]);
    $article = Article::create(['title' => 'A']);
    $article->setCustomField($field->slug, 10);

    expect(fn () => $field->update(['type' => 'text']))
        ->toThrow(InvalidArgumentException::class);
});

it('filters and sorts entities through the custom field query adapters', function (): void {
    $field = CustomField::create([
        'entity_type' => 'article',
        'name' => 'Rank',
        'type' => 'number',
    ]);
    $first = Article::create(['title' => 'First']);
    $second = Article::create(['title' => 'Second']);
    $first->setCustomField($field->slug, 10);
    $second->setCustomField($field->slug, 2);

    $filtered = Article::query();
    (new CustomFieldFilter($field))($filtered, 10, 'cf_rank');

    $sorted = Article::query();
    (new CustomFieldSorter($field))($sorted, false, 'cf_rank');

    expect($filtered->pluck('title')->all())->toBe(['First'])
        ->and($sorted->pluck('title')->all())->toBe(['Second', 'First']);
});

it('writes a batch of values atomically and supports complete validation', function (): void {
    $rank = CustomField::create([
        'entity_type' => 'article',
        'name' => 'Rank',
        'type' => 'number',
    ]);
    $email = CustomField::create([
        'entity_type' => 'article',
        'name' => 'Email',
        'type' => 'email',
        'is_required' => true,
    ]);
    $article = Article::create(['title' => 'A']);

    $article->setCustomFields([
        $rank->slug => 10,
        $email->slug => 'person@example.com',
    ], complete: true);

    expect($article->getCustomFields())->toMatchArray([
        'rank' => 10,
        'email' => 'person@example.com',
    ]);

    $other = Article::create(['title' => 'B']);

    expect(fn () => $other->setCustomFields([
        $rank->slug => 20,
        $email->slug => 'invalid',
    ]))->toThrow(ValidationException::class);

    expect($other->getCustomField($rank->slug))->toBeNull();
});
