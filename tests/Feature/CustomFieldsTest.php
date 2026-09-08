<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Validation\ValidationException;
use PlinCode\CustomFields\Facades\CustomFields;
use PlinCode\CustomFields\Models\CustomField;
use Workbench\App\Models\Article;
use Workbench\App\Models\Project;

beforeEach(function (): void {
    CustomFields::registerEntity(Article::class, 'article');
    CustomFields::registerEntity(Project::class, 'project');
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

it('rejects a field without a name', function (): void {
    expect(fn (): CustomField => CustomField::create([
        'entity_type' => 'article',
        'name' => '   ',
        'type' => 'text',
    ]))->toThrow(InvalidArgumentException::class, 'A custom field name cannot be empty.');
});

it('registers the entity in the Laravel morph map', function (): void {
    expect(Relation::getMorphedModel('article'))->toBe(Article::class)
        ->and(Relation::getMorphedModel('project'))->toBe(Project::class);
});

it('refuses to register two models under the same entity key', function (): void {
    expect(fn () => CustomFields::registerEntity(Article::class.'Other', 'article'))
        ->toThrow(InvalidArgumentException::class, 'Custom field entity key [article] is already registered.');
});

it('gives two names that slugify alike two distinct slugs', function (): void {
    $first = CustomField::create(['entity_type' => 'article', 'name' => 'A B', 'type' => 'text']);
    $second = CustomField::create(['entity_type' => 'article', 'name' => 'A-B', 'type' => 'text']);

    expect($first->slug)->toBe('a-b')
        ->and($second->slug)->toBe('a-b-2');
});

it('keeps a generated slug within one hundred characters after a collision', function (): void {
    $name = str_repeat('a', 120);
    $first = CustomField::create(['entity_type' => 'article', 'name' => $name, 'type' => 'text']);
    $second = CustomField::create(['entity_type' => 'article', 'name' => $name.' b', 'type' => 'text']);

    expect(mb_strlen($first->slug))->toBe(100)
        ->and(mb_strlen($second->slug))->toBe(100)
        ->and($second->slug)->toEndWith('a-2')
        ->and($second->slug)->not->toBe($first->slug);
});

it('keeps fields isolated between two host models', function (): void {
    CustomField::create(['entity_type' => 'article', 'name' => 'Rank', 'type' => 'number']);
    CustomField::create(['entity_type' => 'project', 'name' => 'Rank', 'type' => 'text']);

    $article = Article::create(['title' => 'A']);
    $project = Project::create(['title' => 'P']);
    $article->setCustomField('rank', 5);
    $project->setCustomField('rank', 'five');

    expect($article->getCustomFields())->toBe(['rank' => 5])
        ->and($project->getCustomFields())->toBe(['rank' => 'five'])
        ->and(fn () => $article->setCustomField('rank', 'five'))->toThrow(ValidationException::class);
});

it('hides the definitions of one host from the other', function (): void {
    CustomField::create(['entity_type' => 'project', 'name' => 'Budget', 'type' => 'number']);
    $article = Article::create(['title' => 'A']);

    expect($article->getCustomFields())->toBe([])
        ->and(fn () => $article->getCustomField('budget'))
        ->toThrow(InvalidArgumentException::class, 'Custom field [budget] is not defined for model ['.Article::class.'].');
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
        ]))->toThrow(InvalidArgumentException::class, 'Custom field option [retail] cannot be removed.');
});

it('rejects a duplicated option key', function (): void {
    expect(fn (): CustomField => CustomField::create([
        'entity_type' => 'article',
        'name' => 'Sector',
        'type' => 'select',
        'options' => [
            ['key' => 'retail', 'label' => 'Retail', 'is_active' => true],
            ['key' => 'retail', 'label' => 'Retail again', 'is_active' => true],
        ],
    ]))->toThrow(InvalidArgumentException::class, 'Custom field option [retail] is duplicated.');
});

it('rejects an option without a key or a label', function (): void {
    expect(fn (): CustomField => CustomField::create([
        'entity_type' => 'article',
        'name' => 'Sector',
        'type' => 'select',
        'options' => [['key' => 'retail', 'label' => '']],
    ]))->toThrow(InvalidArgumentException::class, 'Custom field options require a key and label.');
});

it('separates every option key from the keys still assignable', function (): void {
    $field = CustomField::create([
        'entity_type' => 'article',
        'name' => 'Sector',
        'type' => 'select',
        'options' => [
            ['key' => 'retail', 'label' => 'Retail', 'is_active' => false],
            ['key' => 'public', 'label' => 'Public', 'is_active' => true],
        ],
    ]);

    expect($field->optionKeys())->toBe(['retail', 'public'])
        ->and($field->activeOptionKeys())->toBe(['public'])
        ->and($field->optionsForInput())->toHaveCount(1);
});

it('protects the stable slug from ordinary updates', function (): void {
    $field = CustomField::create([
        'entity_type' => 'article',
        'name' => 'Sector',
        'type' => 'text',
    ]);

    expect(fn (): bool => $field->update(['slug' => 'changed']))
        ->toThrow(InvalidArgumentException::class, 'A custom field slug and entity cannot be changed.');
});

it('keeps the slug when the name changes', function (): void {
    $field = CustomField::create([
        'entity_type' => 'article',
        'name' => 'Sector',
        'type' => 'text',
    ]);

    $field->update(['name' => 'Market Segment']);

    expect($field->refresh()->slug)->toBe('sector')
        ->and($field->name)->toBe('market segment');
});

it('protects the field type when values already exist', function (): void {
    $field = CustomField::create([
        'entity_type' => 'article',
        'name' => 'Rank',
        'type' => 'number',
    ]);
    Article::create(['title' => 'A'])->setCustomField($field->slug, 10);

    expect(fn (): bool => $field->update(['type' => 'text']))
        ->toThrow(InvalidArgumentException::class, 'A custom field type cannot change while values exist.');
});
