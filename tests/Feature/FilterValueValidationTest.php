<?php

declare(strict_types=1);

use PlinCode\CustomFields\Facades\CustomFields;
use Spatie\QueryBuilder\Exceptions\InvalidFilterValue;
use Workbench\App\Models\Article;

beforeEach(function (): void {
    CustomFields::registerEntity(Article::class, 'article');
});

it('rejects a non numeric value on a number filter', function (): void {
    articleField(['name' => 'Age', 'type' => 'number']);
    articleWith('zero', ['age' => 0]);
    articleWith('adult', ['age' => 30]);

    expect(fn (): array => articleTitles('/?filter[cf_age]=abc'))->toThrow(InvalidFilterValue::class)
        ->and(articleTitles('/?filter[cf_age]=30'))->toBe(['adult']);
});

it('refuses the word true on a number filter instead of reading it as one', function (): void {
    articleField(['name' => 'Age', 'type' => 'number']);
    articleWith('one', ['age' => 1]);
    articleWith('zero', ['age' => 0]);

    expect(fn (): array => articleTitles('/?filter[cf_age]=true'))->toThrow(InvalidFilterValue::class, 'true')
        ->and(fn (): array => articleTitles('/?filter[cf_age]=false'))->toThrow(InvalidFilterValue::class, 'false')
        ->and(fn (): array => articleTitles('/?filter[cf_age]=1,true'))->toThrow(InvalidFilterValue::class)
        ->and(fn (): array => articleTitles('/?filter[cf_age:greater_than]=true'))->toThrow(InvalidFilterValue::class)
        ->and(articleTitles('/?filter[cf_age]=1'))->toBe(['one']);
});

it('refuses the word true on a contains filter instead of searching for one', function (): void {
    articleField(['name' => 'Notes', 'type' => 'text']);
    articleWith('one', ['notes' => 'number 1']);

    expect(fn (): array => articleTitles('/?filter[cf_notes:contains]=true'))->toThrow(InvalidFilterValue::class)
        ->and(fn (): array => articleTitles('/?filter[cf_notes:contains]=false'))->toThrow(InvalidFilterValue::class)
        ->and(articleTitles('/?filter[cf_notes:contains]=1'))->toBe(['one']);
});

it('rejects an impossible date on a date filter', function (): void {
    articleField(['name' => 'Due', 'type' => 'date']);
    articleWith('a', ['due' => '2024-01-01']);

    expect(fn (): array => articleTitles('/?filter[cf_due]=2024-13-45'))->toThrow(InvalidFilterValue::class)
        ->and(fn (): array => articleTitles('/?filter[cf_due:greater_than]=zzz'))->toThrow(InvalidFilterValue::class)
        ->and(articleTitles('/?filter[cf_due]=2024-01-01'))->toBe(['a']);
});

it('rejects a non numeric bound on a decimal comparison', function (): void {
    articleField(['name' => 'Price', 'type' => 'decimal']);
    articleWith('cheap', ['price' => '5.50']);
    articleWith('dear', ['price' => '50.00']);

    expect(fn (): array => articleTitles('/?filter[cf_price:greater_than]=zzz'))->toThrow(InvalidFilterValue::class)
        ->and(articleTitles('/?filter[cf_price:greater_than]=10'))->toBe(['dear']);
});

it('rejects a range with one invalid bound', function (): void {
    articleField(['name' => 'Due', 'type' => 'date']);
    articleWith('a', ['due' => '2024-01-01']);
    articleWith('b', ['due' => '2024-06-01']);

    expect(fn (): array => articleTitles('/?filter[cf_due:between]=2024-01-01,zzz'))->toThrow(InvalidFilterValue::class)
        ->and(fn (): array => articleTitles('/?filter[cf_due:between]=zzz,2024-12-01'))->toThrow(InvalidFilterValue::class)
        ->and(articleTitles('/?filter[cf_due:between]=2024-05-01,2024-12-01'))->toBe(['b']);
});

it('rejects a list with one invalid element', function (): void {
    articleField(['name' => 'Rank', 'type' => 'number']);
    articleWith('a', ['rank' => 1]);
    articleWith('c', ['rank' => 9]);

    expect(fn (): array => articleTitles('/?filter[cf_rank:in]=1,abc'))->toThrow(InvalidFilterValue::class)
        ->and(fn (): array => articleTitles('/?filter[cf_rank][]=1&filter[cf_rank][]=abc'))->toThrow(InvalidFilterValue::class)
        ->and(articleTitles('/?filter[cf_rank:in]=1,9'))->toBe(['a', 'c']);
});

it('rejects a boolean word and a select key outside the options', function (): void {
    articleField(['name' => 'Flag', 'type' => 'boolean']);
    articleField(['name' => 'Status', 'type' => 'select', 'options' => [['key' => 'open', 'label' => 'Open']]]);
    articleWith('on', ['flag' => true, 'status' => 'open']);

    expect(fn (): array => articleTitles('/?filter[cf_flag]=maybe'))->toThrow(InvalidFilterValue::class)
        ->and(fn (): array => articleTitles('/?filter[cf_status]=closed'))->toThrow(InvalidFilterValue::class)
        ->and(articleTitles('/?filter[cf_status]=open'))->toBe(['on']);
});

it('still applies a presence filter with a value that is not typed', function (): void {
    articleField(['name' => 'Rank', 'type' => 'number']);
    articleWith('answered', ['rank' => 1]);
    Article::create(['title' => 'missing']);

    expect(articleTitles('/?filter[cf_rank:is_null]=yes'))->toBe(['missing'])
        ->and(articleTitles('/?filter[cf_rank:is_not_null]=yes'))->toBe(['answered'])
        ->and(articleTitles('/?filter[cf_rank:is_null]=abc'))->toBe(['answered']);
});

it('rejects an empty value on every typed filter', function (): void {
    articleField(['name' => 'Age', 'type' => 'number']);
    articleField(['name' => 'Due', 'type' => 'date']);
    articleWith('zero', ['age' => 0, 'due' => '2024-01-01']);

    expect(fn (): array => articleTitles('/?filter[cf_age]='))->toThrow(InvalidFilterValue::class)
        ->and(fn (): array => articleTitles('/?filter[cf_due]='))->toThrow(InvalidFilterValue::class)
        ->and(fn (): array => articleTitles('/?filter[cf_due:between]=,2024-01-31'))->toThrow(InvalidFilterValue::class)
        ->and(fn (): array => articleTitles('/?filter[cf_age]=1,,2'))->toThrow(InvalidFilterValue::class);
});
