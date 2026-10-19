<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use PlinCode\CustomFields\Facades\CustomFields;
use PlinCode\CustomFields\Models\CustomField;
use Spatie\QueryBuilder\QueryBuilder;
use Workbench\App\Models\Article;

beforeEach(function (): void {
    CustomFields::registerEntity(Article::class, 'article');

    CustomField::create([
        'entity_type' => 'article',
        'type' => 'select',
        'name' => 'Featured',
        'options' => [
            ['key' => '0', 'label' => 'No'],
            ['key' => '1', 'label' => 'Yes'],
        ],
    ]);
});

it('accepts the option key zero on write and reads it back', function (): void {
    $article = Article::create(['title' => 'A']);

    $article->setCustomField('featured', '0');

    expect($article->getCustomField('featured'))->toBe('0');

    $article->setCustomField('featured', '1');

    expect($article->getCustomField('featured'))->toBe('1');
});

it('still rejects a value that is not an option key', function (): void {
    $article = Article::create(['title' => 'A']);

    expect(fn () => $article->setCustomField('featured', '2'))->toThrow(ValidationException::class);
});

it('filters on the option key zero', function (): void {
    Article::create(['title' => 'Off'])->setCustomField('featured', '0');
    Article::create(['title' => 'On'])->setCustomField('featured', '1');

    $titles = QueryBuilder::for(Article::class, Request::create('/?filter[cf_featured]=0'))
        ->allowedFilters(...CustomFields::filtersFor(Article::class))
        ->pluck('title')
        ->all();

    expect($titles)->toBe(['Off']);
});
