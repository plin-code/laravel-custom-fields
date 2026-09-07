<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Validation\ValidationException;
use PlinCode\CustomFields\Facades\CustomFields;
use PlinCode\CustomFields\Models\CustomField;
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
