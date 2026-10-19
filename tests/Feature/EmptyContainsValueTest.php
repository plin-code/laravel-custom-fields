<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use PlinCode\CustomFields\Facades\CustomFields;
use PlinCode\CustomFields\Models\CustomField;
use Spatie\QueryBuilder\Exceptions\InvalidFilterValue;
use Spatie\QueryBuilder\QueryBuilder;
use Workbench\App\Models\Article;

beforeEach(function (): void {
    CustomFields::registerEntity(Article::class, 'article');
});

/** @return array<int, string> */
function containsTitles(string $url): array
{
    return QueryBuilder::for(Article::class, Request::create($url))
        ->allowedFilters(...CustomFields::filtersFor(Article::class))
        ->pluck('title')
        ->all();
}

it('rejects an empty value on a contains filter', function (): void {
    CustomField::create(['name' => 'Notes', 'type' => 'textarea', 'entity_type' => 'article']);
    Article::create(['title' => 'alpha'])->setCustomFields(['notes' => 'a']);
    Article::create(['title' => 'beta'])->setCustomFields(['notes' => 'b']);

    expect(fn (): array => containsTitles('/?filter[cf_notes:contains]='))->toThrow(InvalidFilterValue::class)
        ->and(fn (): array => containsTitles('/?filter[cf_notes:contains]=a,,b'))->toThrow(InvalidFilterValue::class)
        ->and(fn (): array => containsTitles('/?filter[cf_notes:contains]=a,'))->toThrow(InvalidFilterValue::class)
        ->and(fn (): array => containsTitles('/?filter[cf_notes:contains]=false'))->toThrow(InvalidFilterValue::class)
        ->and(containsTitles('/?filter[cf_notes:contains]=true'))->toBe([])
        ->and(containsTitles('/?filter[cf_notes:contains]=a'))->toBe(['alpha'])
        ->and(containsTitles('/?filter[cf_notes:contains]=a,b'))->toBe(['alpha', 'beta']);
});

it('keeps the multiselect contains filters working', function (): void {
    CustomField::create(['name' => 'Tags', 'type' => 'multiselect', 'entity_type' => 'article', 'options' => [
        ['key' => 'a', 'label' => 'A', 'is_active' => true],
        ['key' => 'b', 'label' => 'B', 'is_active' => true],
    ]]);
    Article::create(['title' => 'one'])->setCustomFields(['tags' => ['a']]);
    Article::create(['title' => 'two'])->setCustomFields(['tags' => ['a', 'b']]);

    expect(containsTitles('/?filter[cf_tags:contains_any]=b'))->toBe(['two'])
        ->and(containsTitles('/?filter[cf_tags:contains_all]=a,b'))->toBe(['two']);
});
