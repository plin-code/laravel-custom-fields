<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PlinCode\CustomFields\Exceptions\InvalidFilterValueException;
use PlinCode\CustomFields\Facades\CustomFields;
use PlinCode\CustomFields\Models\CustomField;
use Spatie\QueryBuilder\Exceptions\InvalidFilterValue;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Workbench\App\Models\Article;

beforeEach(function (): void {
    CustomFields::registerEntity(Article::class, 'article');

    CustomField::create(['entity_type' => 'article', 'name' => 'Age', 'type' => 'number']);
    CustomField::create(['entity_type' => 'article', 'name' => 'Due', 'type' => 'date']);

    Route::get('/articles', fn (Request $request) => QueryBuilder::for(Article::class, $request)
        ->allowedFilters(...CustomFields::filtersFor(Article::class))
        ->pluck('title'))->middleware(ConvertEmptyStringsToNull::class);
});

it('is a spatie invalid filter value that renders as an http 400', function (): void {
    $exception = InvalidFilterValueException::make('abc');

    expect($exception)->toBeInstanceOf(InvalidFilterValue::class)
        ->toBeInstanceOf(HttpExceptionInterface::class)
        ->and($exception->getStatusCode())->toBe(400)
        ->and($exception->getHeaders())->toBe([])
        ->and($exception->getMessage())->toBe(InvalidFilterValue::make('abc')->getMessage());
});

it('answers a malformed filter value with a 400 and a valid one with a 200', function (): void {
    Article::create(['title' => 'adult'])->setCustomFields(['age' => 1]);

    $this->getJson('/articles?filter[cf_age]=abc')->assertStatus(400);
    $this->getJson('/articles?filter[cf_age]=1')->assertOk()->assertExactJson(['adult']);
});

it('answers a bad between bound with a 400', function (): void {
    $this->getJson('/articles?filter[cf_due:between]=2024-01-01,zzz')->assertStatus(400);
    $this->getJson('/articles?filter[cf_due:between]=2024-01-01,2024-12-31')->assertOk();
});

it('answers a null element inside a list with a 400', function (): void {
    Article::create(['title' => 'adult'])->setCustomFields(['age' => 1]);

    $this->getJson('/articles?filter[cf_age][]=')->assertStatus(400);
    $this->getJson('/articles?filter[cf_age:in][]=1&filter[cf_age:in][]=')->assertStatus(400);
    $this->getJson('/articles?filter[cf_age:between][]=1&filter[cf_age:between][]=')->assertStatus(400);
    $this->getJson('/articles?filter[cf_age][]=1&filter[cf_age][]=2')->assertOk()->assertExactJson(['adult']);
});
