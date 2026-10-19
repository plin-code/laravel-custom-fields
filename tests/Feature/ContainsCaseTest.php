<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use PlinCode\CustomFields\Facades\CustomFields;
use PlinCode\CustomFields\Models\CustomField;
use Spatie\QueryBuilder\QueryBuilder;
use Workbench\App\Models\Article;

beforeEach(function (): void {
    CustomFields::registerEntity(Article::class, 'article');

    CustomField::create(['name' => 'Notes', 'type' => 'text', 'entity_type' => 'article']);
});

/** @return array<int, string> */
function caseTitles(string $url): array
{
    return QueryBuilder::for(Article::class, Request::create($url))
        ->allowedFilters(...CustomFields::filtersFor(Article::class))
        ->orderBy('title')
        ->pluck('title')
        ->all();
}

it('matches a contains term regardless of the letter case', function (): void {
    Article::create(['title' => 'upper'])->setCustomFields(['notes' => 'ABC']);
    Article::create(['title' => 'other'])->setCustomFields(['notes' => 'xyz']);

    expect(caseTitles('/?filter[cf_notes:contains]=abc'))->toBe(['upper'])
        ->and(caseTitles('/?filter[cf_notes:contains]=ABC'))->toBe(['upper']);
});

it('matches the wildcards and the backslash of a contains term literally', function (): void {
    Article::create(['title' => 'percent'])->setCustomFields(['notes' => '50% off']);
    Article::create(['title' => 'underscore'])->setCustomFields(['notes' => 'a_b']);
    Article::create(['title' => 'backslash'])->setCustomFields(['notes' => 'c\\d']);
    Article::create(['title' => 'plain'])->setCustomFields(['notes' => '500 offer axb cd']);

    expect(caseTitles('/?filter[cf_notes:contains]='.urlencode('0% o')))->toBe(['percent'])
        ->and(caseTitles('/?filter[cf_notes:contains]='.urlencode('a_b')))->toBe(['underscore'])
        ->and(caseTitles('/?filter[cf_notes:contains]='.urlencode('c\\d')))->toBe(['backslash'])
        ->and(caseTitles('/?filter[cf_notes:contains]='.urlencode('%')))->toBe(['percent'])
        ->and(caseTitles('/?filter[cf_notes:contains]='.urlencode('_')))->toBe(['underscore'])
        ->and(caseTitles('/?filter[cf_notes:contains]='.urlencode('\\')))->toBe(['backslash']);
});

it('keeps or semantics across the terms of a contains filter', function (): void {
    Article::create(['title' => 'first'])->setCustomFields(['notes' => 'Alpha']);
    Article::create(['title' => 'second'])->setCustomFields(['notes' => 'beta']);
    Article::create(['title' => 'third'])->setCustomFields(['notes' => 'gamma']);

    expect(caseTitles('/?filter[cf_notes:contains]=alpha,BETA'))->toBe(['first', 'second']);
});

it('returns an empty list when no value contains the term', function (): void {
    Article::create(['title' => 'first'])->setCustomFields(['notes' => 'Alpha']);

    expect(caseTitles('/?filter[cf_notes:contains]=zzz'))->toBe([]);
});
