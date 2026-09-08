<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PlinCode\CustomFields\Facades\CustomFields;
use PlinCode\CustomFields\Models\CustomField;
use PlinCode\CustomFields\Models\CustomFieldValue;
use PlinCode\CustomFields\Query\CustomFieldFilter;
use PlinCode\CustomFields\Query\CustomFieldSorter;
use PlinCode\EloquentSorts\Sorts\RelationSorter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\Exceptions\InvalidFilterQuery;
use Spatie\QueryBuilder\Exceptions\InvalidFilterValue;
use Spatie\QueryBuilder\QueryBuilder;
use Workbench\App\Models\Article;
use Workbench\App\Models\Author;

beforeEach(function (): void {
    CustomFields::registerEntity(Article::class, 'article');
});

/** @param array<string, mixed> $attributes */
function queryable(array $attributes): CustomField
{
    return CustomField::create($attributes + ['entity_type' => 'article', 'type' => 'text']);
}

/** @return array<int, string> */
function titlesFor(string $url): array
{
    return QueryBuilder::for(Article::class, Request::create($url))
        ->allowedFilters(...CustomFields::filtersFor(Article::class))
        ->allowedSorts(...CustomFields::sortsFor(Article::class))
        ->pluck('title')
        ->all();
}

/** @return array<int, string> */
function filterNames(): array
{
    return array_map(
        static fn (object $filter): string => (string) $filter->getName(),
        CustomFields::filtersFor(Article::class),
    );
}

/** @return array<int, string> */
function sortNames(): array
{
    return array_map(
        static fn (object $sort): string => (string) $sort->getName(),
        CustomFields::sortsFor(Article::class),
    );
}

/** @param array<string, mixed> $values */
function articleWith(string $title, array $values): Article
{
    $article = Article::create(['title' => $title]);
    $article->setCustomFields($values);

    return $article;
}

it('exposes one filter per declared operation of a field', function (): void {
    queryable(['name' => 'Rank', 'type' => 'number']);

    expect(filterNames())->toBe([
        'cf_rank',
        'cf_rank:in',
        'cf_rank:greater_than',
        'cf_rank:less_than',
        'cf_rank:between',
        'cf_rank:is_null',
        'cf_rank:is_not_null',
    ])->and(sortNames())->toBe(['cf_rank']);
});

it('never exposes an equality filter on a multiselect', function (): void {
    queryable(['name' => 'Tags', 'type' => 'multiselect', 'options' => [
        ['key' => 'a', 'label' => 'A', 'is_active' => true],
        ['key' => 'b', 'label' => 'B', 'is_active' => true],
    ]]);
    articleWith('One', ['tags' => ['a']]);

    expect(filterNames())->toBe([
        'cf_tags:contains_any',
        'cf_tags:contains_all',
        'cf_tags:is_null',
        'cf_tags:is_not_null',
    ])
        ->and(sortNames())->toBe([])
        ->and(fn (): array => titlesFor('/?filter[cf_tags]=a'))->toThrow(InvalidFilterQuery::class);
});

it('matches a multiselect on the keys it holds', function (): void {
    queryable(['name' => 'Tags', 'type' => 'multiselect', 'options' => [
        ['key' => 'a', 'label' => 'A', 'is_active' => true],
        ['key' => 'b', 'label' => 'B', 'is_active' => true],
    ]]);
    articleWith('One', ['tags' => ['a']]);
    articleWith('Two', ['tags' => ['a', 'b']]);
    articleWith('Three', ['tags' => []]);

    expect(titlesFor('/?filter[cf_tags:contains_any]=a'))->toBe(['One', 'Two'])
        ->and(titlesFor('/?filter[cf_tags:contains_any]=a,b'))->toBe(['One', 'Two'])
        ->and(titlesFor('/?filter[cf_tags:contains_all]=a,b'))->toBe(['Two'])
        ->and(titlesFor('/?filter[cf_tags:is_not_null]=1'))->toBe(['One', 'Two', 'Three'])
        ->and(titlesFor('/?filter[cf_tags:is_null]=1'))->toBe([]);
});

it('refuses an operation the field type does not declare', function (): void {
    $tags = queryable(['name' => 'Tags', 'type' => 'multiselect', 'options' => [
        ['key' => 'a', 'label' => 'A', 'is_active' => true],
    ]]);
    $notes = queryable(['name' => 'Notes', 'type' => 'textarea']);

    expect(fn (): CustomFieldFilter => new CustomFieldFilter($tags, CustomFieldFilter::EQUALS))
        ->toThrow(InvalidArgumentException::class, 'Custom field [tags] does not declare the query operation [equals].')
        ->and(fn (): CustomFieldSorter => new CustomFieldSorter($tags))
        ->toThrow(InvalidArgumentException::class, 'Custom field [tags] does not declare the query operation [sort].')
        ->and(fn (): CustomFieldSorter => new CustomFieldSorter($notes))
        ->toThrow(InvalidArgumentException::class, 'Custom field [notes] does not declare the query operation [sort].');
});

it('refuses an operation the filter cannot serve', function (): void {
    $rank = queryable(['name' => 'Rank', 'type' => 'number']);

    expect(fn (): CustomFieldFilter => new CustomFieldFilter($rank, 'regex'))
        ->toThrow(InvalidArgumentException::class, 'Custom field query operation [regex] is not supported.')
        ->and(CustomFieldFilter::supports('regex'))->toBeFalse()
        ->and(CustomFieldFilter::supports(CustomFieldFilter::BETWEEN))->toBeTrue()
        ->and((new CustomFieldFilter($rank))->operation())->toBe(CustomFieldFilter::EQUALS);
});

it('compares and ranges a number field', function (): void {
    queryable(['name' => 'Rank', 'type' => 'number']);
    articleWith('a', ['rank' => 1]);
    articleWith('b', ['rank' => 5]);
    articleWith('c', ['rank' => 9]);

    expect(titlesFor('/?filter[cf_rank]=5'))->toBe(['b'])
        ->and(titlesFor('/?filter[cf_rank:in]=1,9'))->toBe(['a', 'c'])
        ->and(titlesFor('/?filter[cf_rank:greater_than]=4'))->toBe(['b', 'c'])
        ->and(titlesFor('/?filter[cf_rank:less_than]=5'))->toBe(['a'])
        ->and(titlesFor('/?filter[cf_rank:between]=2,9'))->toBe(['b', 'c']);
});

it('compares and ranges a date field', function (): void {
    queryable(['name' => 'Due', 'type' => 'date']);
    articleWith('a', ['due' => '2024-01-01']);
    articleWith('b', ['due' => '2024-06-01']);
    articleWith('c', ['due' => '2024-12-01']);

    expect(titlesFor('/?filter[cf_due]=2024-06-01'))->toBe(['b'])
        ->and(titlesFor('/?filter[cf_due:greater_than]=2024-05-01'))->toBe(['b', 'c'])
        ->and(titlesFor('/?filter[cf_due:less_than]=2024-05-01'))->toBe(['a'])
        ->and(titlesFor('/?filter[cf_due:between]=2024-04-01,2024-12-01'))->toBe(['b', 'c']);
});

it('rejects a range that does not carry two bounds', function (): void {
    queryable(['name' => 'Rank', 'type' => 'number']);

    expect(fn (): array => titlesFor('/?filter[cf_rank:between]=2'))->toThrow(InvalidFilterValue::class)
        ->and(fn (): array => titlesFor('/?filter[cf_rank:greater_than]=2,3'))->toThrow(InvalidFilterValue::class);
});

it('reads a boolean filter written as text', function (): void {
    queryable(['name' => 'Flag', 'type' => 'boolean']);
    articleWith('on', ['flag' => true]);
    articleWith('off', ['flag' => false]);

    expect(titlesFor('/?filter[cf_flag]=true'))->toBe(['on'])
        ->and(titlesFor('/?filter[cf_flag]=1'))->toBe(['on'])
        ->and(titlesFor('/?filter[cf_flag]=false'))->toBe(['off'])
        ->and(titlesFor('/?filter[cf_flag]=0'))->toBe(['off']);
});

it('treats a missing row and a null column alike for presence', function (): void {
    $rank = queryable(['name' => 'Rank', 'type' => 'number']);
    articleWith('answered', ['rank' => 1]);
    $blank = Article::create(['title' => 'blank']);
    Article::create(['title' => 'missing']);

    CustomFieldValue::create([
        'custom_field_id' => $rank->getKey(),
        'valuable_type' => 'article',
        'valuable_id' => $blank->getKey(),
    ]);

    expect(titlesFor('/?filter[cf_rank:is_null]=1'))->toBe(['blank', 'missing'])
        ->and(titlesFor('/?filter[cf_rank:is_not_null]=1'))->toBe(['answered'])
        ->and(titlesFor('/?filter[cf_rank:is_null]=0'))->toBe(['answered']);
});

it('matches a literal wildcard through the contains operation', function (): void {
    queryable(['name' => 'Notes', 'type' => 'text']);
    articleWith('percent', ['notes' => '50% off']);
    articleWith('underscore', ['notes' => 'off_x']);
    articleWith('plain', ['notes' => 'offax']);
    articleWith('backslash', ['notes' => 'back\\slash']);
    articleWith('bang', ['notes' => 'bang!']);

    expect(titlesFor('/?filter[cf_notes:contains]=50%'))->toBe(['percent'])
        ->and(titlesFor('/?filter[cf_notes:contains]=off_x'))->toBe(['underscore'])
        ->and(titlesFor('/?filter[cf_notes:contains]=%%'))->toBe([])
        ->and(titlesFor('/?filter[cf_notes:contains]='.urlencode('back\\slash')))->toBe(['backslash'])
        ->and(titlesFor('/?filter[cf_notes:contains]='.urlencode('bang!')))->toBe(['bang']);
});

it('groups the values of a contains filter as an or', function (): void {
    queryable(['name' => 'Notes', 'type' => 'text']);
    articleWith('first', ['notes' => 'alpha one']);
    articleWith('second', ['notes' => 'beta two']);
    articleWith('third', ['notes' => 'gamma three']);

    expect(titlesFor('/?filter[cf_notes:contains]=alpha,beta'))->toBe(['first', 'second']);
});

it('places the hosts without a value last in both directions', function (): void {
    queryable(['name' => 'Rank', 'type' => 'number']);
    articleWith('a', ['rank' => 3]);
    articleWith('b', ['rank' => 1]);
    articleWith('c', ['rank' => 2]);
    Article::create(['title' => 'none']);

    expect(titlesFor('/?sort=cf_rank'))->toBe(['b', 'c', 'a', 'none'])
        ->and(titlesFor('/?sort=-cf_rank'))->toBe(['a', 'c', 'b', 'none']);
});

it('leaves the select, the grouping and the order of the caller alone', function (): void {
    $rank = queryable(['name' => 'Rank', 'type' => 'number']);
    articleWith('a', ['rank' => 3]);
    articleWith('b', ['rank' => 1]);
    Article::create(['title' => 'none']);

    $query = Article::query()
        ->select('articles.id', 'articles.title')
        ->selectRaw('count(*) as value_count')
        ->groupBy('articles.id', 'articles.title')
        ->orderBy('articles.title');

    (new CustomFieldSorter($rank))($query, false, 'cf_rank');

    expect($query->get()->pluck('value_count')->all())->toBe([1, 1, 1])
        ->and($query->get()->pluck('title')->all())->toBe(['a', 'b', 'none']);
});

it('composes a custom field sort with a sorter of the sibling package', function (): void {
    queryable(['name' => 'Rank', 'type' => 'number']);
    $zoe = Author::create(['name' => 'zoe']);
    $ada = Author::create(['name' => 'ada']);
    Article::create(['title' => 'one', 'author_id' => $zoe->id])->setCustomField('rank', 1);
    Article::create(['title' => 'two', 'author_id' => $ada->id])->setCustomField('rank', 9);
    Article::create(['title' => 'three', 'author_id' => $ada->id])->setCustomField('rank', 2);

    $titles = QueryBuilder::for(Article::class, Request::create('/?sort=author,cf_rank'))
        ->allowedSorts(
            AllowedSort::custom('author', new RelationSorter('authors', 'author_id', 'name')),
            ...CustomFields::sortsFor(Article::class),
        )
        ->pluck('title')
        ->all();

    expect($titles)->toBe(['three', 'two', 'one']);
});

it('follows the configured key prefix', function (): void {
    config()->set('laravel-custom-fields.key_prefix', 'x-');
    queryable(['name' => 'Rank', 'type' => 'number']);

    expect(CustomFields::keyPrefix())->toBe('x-')
        ->and(CustomFields::filterName('rank'))->toBe('x-rank')
        ->and(CustomFields::filterName('rank', CustomFieldFilter::BETWEEN))->toBe('x-rank:between')
        ->and(CustomFields::sortName('rank'))->toBe('x-rank')
        ->and(filterNames())->toContain('x-rank', 'x-rank:between')
        ->and(sortNames())->toBe(['x-rank']);
});

it('reads the definitions once when filters and sorts are asked together', function (): void {
    queryable(['name' => 'Rank', 'type' => 'number']);

    DB::enableQueryLog();
    $options = CustomFields::queryOptionsFor(Article::class);
    $together = count(DB::getQueryLog());

    DB::flushQueryLog();
    CustomFields::filtersFor(Article::class);
    CustomFields::sortsFor(Article::class);
    $apart = count(DB::getQueryLog());

    expect($together)->toBe(1)
        ->and($apart)->toBe(2)
        ->and($options['filters'])->toHaveCount(7)
        ->and($options['sorts'])->toHaveCount(1);
});

it('offers neither a filter nor a sort for a deactivated field', function (): void {
    queryable(['name' => 'Rank', 'type' => 'number', 'is_active' => false]);

    expect(filterNames())->toBe([])
        ->and(sortNames())->toBe([]);
});
