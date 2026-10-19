<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use PlinCode\CustomFields\Facades\CustomFields;
use PlinCode\CustomFields\Models\CustomField;
use Spatie\QueryBuilder\QueryBuilder;
use Workbench\App\Models\Article;

beforeEach(function (): void {
    CustomFields::registerEntity(Article::class, 'article');
    config(['app.timezone' => 'UTC']);
});

function normalisedField(string $type): void
{
    CustomField::create(['name' => 'When', 'entity_type' => 'article', 'type' => $type]);
}

function storedArticle(string $title, mixed $value): Article
{
    $article = Article::create(['title' => $title]);
    $article->setCustomField('when', $value);

    return $article;
}

/** @return array<int, string> */
function normalisedTitles(string $url): array
{
    return QueryBuilder::for(Article::class, Request::create($url))
        ->allowedFilters(...CustomFields::filtersFor(Article::class))
        ->pluck('title')
        ->all();
}

it('stores every accepted date form as a calendar date', function (string $input): void {
    normalisedField('date');

    expect(storedArticle('a', $input)->fresh()->getCustomField('when'))->toBe('2024-01-15');
})->with([
    'long form' => '15 January 2024',
    'us form' => '01/15/2024',
    'utc timestamp' => '2024-01-15T10:00:00Z',
    'offset timestamp' => '2024-01-15T10:00:00+02:00',
]);

it('converts a datetime with an offset to the app timezone', function (): void {
    normalisedField('datetime');

    expect(storedArticle('a', '2024-01-15T10:00:00+02:00')->fresh()->getCustomField('when'))->toBe('2024-01-15 08:00:00');
});

it('round trips a datetime without an offset unchanged', function (): void {
    normalisedField('datetime');

    expect(storedArticle('a', '2024-01-15 10:30:45')->fresh()->getCustomField('when'))->toBe('2024-01-15 10:30:45');
});

it('converts a utc datetime to a configured app timezone', function (): void {
    config(['app.timezone' => 'Europe/Rome']);
    normalisedField('datetime');

    expect(storedArticle('a', '2024-01-15T10:30:45Z')->fresh()->getCustomField('when'))->toBe('2024-01-15 11:30:45');
});

it('matches a row stored from a non canonical date by equality and by range', function (): void {
    normalisedField('date');
    storedArticle('early', '01/10/2024');
    storedArticle('target', '15 January 2024');
    storedArticle('late', '2024-01-20T10:00:00Z');

    expect(Article::query()->whereCustomField('when', '2024-01-15T00:00:00Z')->pluck('title')->all())->toBe(['target'])
        ->and(normalisedTitles('/?filter[cf_when]=2024-01-15T00:00:00Z'))->toBe(['target'])
        ->and(normalisedTitles('/?filter[cf_when:greater_than]=2024-01-15T00:00:00Z'))->toBe(['late']);
});

it('matches a datetime row stored with an offset', function (): void {
    normalisedField('datetime');
    storedArticle('early', '2024-01-15T08:00:00+02:00');
    storedArticle('target', '2024-01-15T10:00:00+02:00');

    expect(normalisedTitles('/?filter[cf_when]=2024-01-15T08:00:00Z'))->toBe(['target'])
        ->and(normalisedTitles('/?filter[cf_when:greater_than]=2024-01-15T07:00:00Z'))->toBe(['target']);
});

it('accepts a carbon instance', function (): void {
    normalisedField('date');
    $article = storedArticle('a', Date::parse('2024-01-15 23:30:00'));

    expect($article->fresh()->getCustomField('when'))->toBe('2024-01-15');
});

it('clears a date and a datetime with null', function (string $type): void {
    normalisedField($type);
    $article = storedArticle('a', '2024-01-15 10:00:00');

    $article->setCustomField('when', null);

    expect($article->fresh()->getCustomField('when'))->toBeNull();
})->with(['date', 'datetime']);
