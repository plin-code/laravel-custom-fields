<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use PlinCode\CustomFields\Facades\CustomFields;
use PlinCode\CustomFields\Models\CustomField;
use Workbench\App\Models\Article;

beforeEach(function (): void {
    CustomFields::registerEntity(Article::class, 'article');
    CustomField::create(['entity_type' => 'article', 'type' => 'decimal', 'name' => 'Price']);
});

it('rejects a value the column cannot hold', function (string $value): void {
    $article = Article::create(['title' => 'A']);

    expect(fn () => $article->setCustomField('price', $value))->toThrow(ValidationException::class)
        ->and($article->getCustomField('price'))->toBeNull();
})->with([
    'exponent notation' => '1e20',
    'fifteen integer digits' => '123456789012345',
    'seven fraction digits' => '1.1234567',
    'surrounding whitespace' => ' 5 ',
    'negative beyond the scale' => '-99999999999999.9999991',
]);

it('reads back exactly what the column holds', function (string $written, string $read): void {
    $article = Article::create(['title' => 'A']);

    $article->setCustomField('price', $written);

    expect($article->getCustomField('price'))->toBe($read);
})->with([
    'a negative fraction' => ['-0.5', '-0.5'],
    'a short fraction' => ['0.1', '0.1'],
    'a leading plus' => ['+5', '5'],
    'trailing zeros' => ['12.500000', '12.5'],
]);

it('accepts the bounds of the column', function (string $value): void {
    $article = Article::create(['title' => 'A']);

    expect(fn () => $article->setCustomField('price', $value))->not->toThrow(ValidationException::class);
})->with([
    'the upper bound' => '99999999999999.999999',
    'the lower bound' => '-99999999999999.999999',
]);

it('clears the value with null', function (): void {
    $article = Article::create(['title' => 'A']);
    $article->setCustomField('price', '5');

    $article->setCustomField('price', null);

    expect($article->getCustomField('price'))->toBeNull();
});
