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

/** @param array<string, mixed> $attributes */
function filterField(array $attributes): CustomField
{
    return CustomField::create($attributes + ['entity_type' => 'article']);
}

/** @return array<int, string> */
function filterTitles(string $url): array
{
    return QueryBuilder::for(Article::class, Request::create($url))
        ->allowedFilters(...CustomFields::filtersFor(Article::class))
        ->pluck('title')
        ->all();
}

/** @param array<string, mixed> $values */
function filterArticle(string $title, array $values): void
{
    Article::create(['title' => $title])->setCustomFields($values);
}

it('rejects a non numeric value on a number filter', function (): void {
    filterField(['name' => 'Age', 'type' => 'number']);
    filterArticle('zero', ['age' => 0]);
    filterArticle('adult', ['age' => 30]);

    expect(fn (): array => filterTitles('/?filter[cf_age]=abc'))->toThrow(InvalidFilterValue::class)
        ->and(filterTitles('/?filter[cf_age]=30'))->toBe(['adult']);
});

it('rejects an impossible date on a date filter', function (): void {
    filterField(['name' => 'Due', 'type' => 'date']);
    filterArticle('a', ['due' => '2024-01-01']);

    expect(fn (): array => filterTitles('/?filter[cf_due]=2024-13-45'))->toThrow(InvalidFilterValue::class)
        ->and(fn (): array => filterTitles('/?filter[cf_due:greater_than]=zzz'))->toThrow(InvalidFilterValue::class)
        ->and(filterTitles('/?filter[cf_due]=2024-01-01'))->toBe(['a']);
});

it('rejects a non numeric bound on a decimal comparison', function (): void {
    filterField(['name' => 'Price', 'type' => 'decimal']);
    filterArticle('cheap', ['price' => '5.50']);
    filterArticle('dear', ['price' => '50.00']);

    expect(fn (): array => filterTitles('/?filter[cf_price:greater_than]=zzz'))->toThrow(InvalidFilterValue::class)
        ->and(filterTitles('/?filter[cf_price:greater_than]=10'))->toBe(['dear']);
});

it('rejects a range with one invalid bound', function (): void {
    filterField(['name' => 'Due', 'type' => 'date']);
    filterArticle('a', ['due' => '2024-01-01']);
    filterArticle('b', ['due' => '2024-06-01']);

    expect(fn (): array => filterTitles('/?filter[cf_due:between]=2024-01-01,zzz'))->toThrow(InvalidFilterValue::class)
        ->and(fn (): array => filterTitles('/?filter[cf_due:between]=zzz,2024-12-01'))->toThrow(InvalidFilterValue::class)
        ->and(filterTitles('/?filter[cf_due:between]=2024-05-01,2024-12-01'))->toBe(['b']);
});

it('rejects a list with one invalid element', function (): void {
    filterField(['name' => 'Rank', 'type' => 'number']);
    filterArticle('a', ['rank' => 1]);
    filterArticle('c', ['rank' => 9]);

    expect(fn (): array => filterTitles('/?filter[cf_rank:in]=1,abc'))->toThrow(InvalidFilterValue::class)
        ->and(fn (): array => filterTitles('/?filter[cf_rank][]=1&filter[cf_rank][]=abc'))->toThrow(InvalidFilterValue::class)
        ->and(filterTitles('/?filter[cf_rank:in]=1,9'))->toBe(['a', 'c']);
});

it('rejects a boolean word and a select key outside the options', function (): void {
    filterField(['name' => 'Flag', 'type' => 'boolean']);
    filterField(['name' => 'Status', 'type' => 'select', 'options' => [['key' => 'open', 'label' => 'Open']]]);
    filterArticle('on', ['flag' => true, 'status' => 'open']);

    expect(fn (): array => filterTitles('/?filter[cf_flag]=maybe'))->toThrow(InvalidFilterValue::class)
        ->and(fn (): array => filterTitles('/?filter[cf_status]=closed'))->toThrow(InvalidFilterValue::class)
        ->and(filterTitles('/?filter[cf_status]=open'))->toBe(['on']);
});

it('still applies a presence filter with a value that is not typed', function (): void {
    filterField(['name' => 'Rank', 'type' => 'number']);
    filterArticle('answered', ['rank' => 1]);
    Article::create(['title' => 'missing']);

    expect(filterTitles('/?filter[cf_rank:is_null]=yes'))->toBe(['missing'])
        ->and(filterTitles('/?filter[cf_rank:is_not_null]=yes'))->toBe(['answered'])
        ->and(filterTitles('/?filter[cf_rank:is_null]=abc'))->toBe(['answered']);
});

it('rejects an empty value on every typed filter', function (): void {
    filterField(['name' => 'Age', 'type' => 'number']);
    filterField(['name' => 'Due', 'type' => 'date']);
    filterArticle('zero', ['age' => 0, 'due' => '2024-01-01']);

    expect(fn (): array => filterTitles('/?filter[cf_age]='))->toThrow(InvalidFilterValue::class)
        ->and(fn (): array => filterTitles('/?filter[cf_due]='))->toThrow(InvalidFilterValue::class)
        ->and(fn (): array => filterTitles('/?filter[cf_due:between]=,2024-01-31'))->toThrow(InvalidFilterValue::class)
        ->and(fn (): array => filterTitles('/?filter[cf_age]=1,,2'))->toThrow(InvalidFilterValue::class);
});
