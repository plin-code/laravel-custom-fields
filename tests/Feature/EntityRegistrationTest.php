<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\Relation;
use PlinCode\CustomFields\CustomFields;
use Workbench\App\Models\Article;
use Workbench\App\Models\Project;

beforeEach(function (): void {
    Relation::morphMap([], false);
    app()->instance(CustomFields::class, new CustomFields);
});

afterEach(function (): void {
    Relation::morphMap([], false);
});

it('refuses a key that the morph map assigns to another model', function (): void {
    Relation::morphMap(['owned' => Project::class]);

    expect(fn () => app(CustomFields::class)->registerEntity(Article::class, 'owned'))
        ->toThrow(InvalidArgumentException::class, 'Custom field entity key [owned] is already mapped to ['.Project::class.'] in the morph map.')
        ->and(Relation::getMorphedModel('owned'))->toBe(Project::class)
        ->and(Relation::morphMap())->toBe(['owned' => Project::class])
        ->and(app(CustomFields::class)->entities())->toBe([]);
});

it('accepts a key that the morph map already assigns to the same model', function (): void {
    Relation::morphMap(['article' => Article::class]);

    app(CustomFields::class)->registerEntity(Article::class, 'article');

    expect(app(CustomFields::class)->entityKey(Article::class))->toBe('article')
        ->and(Relation::getMorphedModel('article'))->toBe(Article::class);
});

it('treats registering the same model under the same key twice as a no-op', function (): void {
    $fields = app(CustomFields::class);

    $fields->registerEntity(Article::class, 'article', 'Posts');
    $fields->registerEntity(Article::class, 'article', 'Other label');

    expect($fields->entities())->toBe([Article::class => ['key' => 'article', 'label' => 'Posts']])
        ->and(Relation::morphMap())->toBe(['article' => Article::class]);
});

it('refuses to register the same model under a different key', function (): void {
    $fields = app(CustomFields::class);
    $fields->registerEntity(Article::class, 'article');

    expect(fn () => $fields->registerEntity(Article::class, 'post'))
        ->toThrow(InvalidArgumentException::class, 'Model ['.Article::class.'] is already registered for custom fields as [article].')
        ->and($fields->entityKey(Article::class))->toBe('article')
        ->and(Relation::getMorphedModel('post'))->toBeNull();
});

it('registers a fresh key and adds it to the morph map', function (): void {
    app(CustomFields::class)->registerEntity(Article::class, 'article');

    expect(Relation::getMorphedModel('article'))->toBe(Article::class)
        ->and(app(CustomFields::class)->entityKey(Article::class))->toBe('article');
});
