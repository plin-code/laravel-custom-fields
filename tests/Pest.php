<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use PlinCode\CustomFields\Facades\CustomFields;
use PlinCode\CustomFields\Models\CustomField;
use PlinCode\CustomFields\Tests\TestCase;
use Spatie\QueryBuilder\QueryBuilder;
use Workbench\App\Models\Article;

uses(TestCase::class)->in(__DIR__);

/**
 * Defines a field of the article entity, a text one unless the attributes say otherwise.
 *
 * @param  array<string, mixed>  $attributes
 */
function articleField(array $attributes): CustomField
{
    return CustomField::create($attributes + ['entity_type' => 'article', 'type' => 'text']);
}

/**
 * Creates an article and stores the given custom values on it.
 *
 * @param  array<string, mixed>  $values
 */
function articleWith(string $title, array $values): Article
{
    $article = Article::create(['title' => $title]);
    $article->setCustomFields($values);

    return $article;
}

/**
 * Runs the request through the query builder with every custom field filter and sort allowed.
 *
 * @return array<int, string>
 */
function articleTitles(string $url): array
{
    return QueryBuilder::for(Article::class, Request::create($url))
        ->allowedFilters(...CustomFields::filtersFor(Article::class))
        ->allowedSorts(...CustomFields::sortsFor(Article::class))
        ->pluck('title')
        ->all();
}
