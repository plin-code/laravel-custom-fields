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
    config(['app.timezone' => 'UTC']);
    CustomField::create(['name' => 'When', 'entity_type' => 'article', 'type' => 'date']);
});

/** @return array<int, string> */
function dateFilterTitles(string $url): array
{
    return QueryBuilder::for(Article::class, Request::create($url))
        ->allowedFilters(...CustomFields::filtersFor(Article::class))
        ->pluck('title')
        ->all();
}

it('rejects a date filter value that cannot be parsed', function (string $url): void {
    expect(fn (): array => dateFilterTitles($url))->toThrow(InvalidFilterValue::class);
})->with([
    'text' => '/?filter[cf_when]=abc',
    'impossible date' => '/?filter[cf_when]=2024-13-45',
    'range bound' => '/?filter[cf_when:between]=abc,2024-01-31',
]);

it('still filters on a valid date value', function (): void {
    Article::create(['title' => 'early'])->setCustomField('when', '2024-01-05');
    Article::create(['title' => 'target'])->setCustomField('when', '2024-01-15');

    expect(dateFilterTitles('/?filter[cf_when]=2024-01-15'))->toBe(['target'])
        ->and(dateFilterTitles('/?filter[cf_when:greater_than]=2024-01-10'))->toBe(['target']);
});

it('reads a legacy datetime stored with an offset in the app timezone', function (): void {
    $field = CustomField::create(['name' => 'Seen', 'entity_type' => 'article', 'type' => 'datetime']);
    $type = CustomFields::type('datetime');

    expect($type->deserialize('2024-01-15T10:00:00+02:00', $field))->toBe('2024-01-15 08:00:00')
        ->and($type->deserialize(null, $field))->toBeNull();
});

it('reads a naive datetime in the app timezone and shifts one that carries an offset', function (): void {
    config(['app.timezone' => 'Europe/Rome']);
    CustomField::create(['name' => 'Seen', 'entity_type' => 'article', 'type' => 'datetime']);
    $naive = Article::create(['title' => 'naive']);
    $utc = Article::create(['title' => 'utc']);

    $naive->setCustomField('seen', '2024-01-15 10:00:00');
    $utc->setCustomField('seen', '2024-01-15T10:00:00Z');

    expect($naive->fresh()->getCustomField('seen'))->toBe('2024-01-15 10:00:00')
        ->and($utc->fresh()->getCustomField('seen'))->toBe('2024-01-15 11:00:00');
});
