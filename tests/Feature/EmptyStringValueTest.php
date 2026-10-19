<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use PlinCode\CustomFields\Facades\CustomFields;
use PlinCode\CustomFields\Models\CustomField;
use Workbench\App\Models\Article;

beforeEach(function (): void {
    CustomFields::registerEntity(Article::class, 'article');
});

/** @param array<string, mixed> $attributes */
function blankableField(array $attributes): CustomField
{
    return CustomField::create($attributes + ['entity_type' => 'article', 'type' => 'text']);
}

it('clears the stored value when an empty string is written', function (string $type, mixed $stored): void {
    blankableField(['name' => 'Subject', 'type' => $type]);
    $article = Article::create(['title' => 'A']);
    $article->setCustomField('subject', $stored);

    $article->setCustomField('subject', '');

    expect($article->customFieldValues()->count())->toBe(0)
        ->and($article->getCustomField('subject'))->toBeNull();
})->with([
    'number' => ['number', 7],
    'boolean' => ['boolean', true],
    'date' => ['date', '2026-03-01'],
    'decimal' => ['decimal', '12.50'],
    'text' => ['text', 'Sector'],
]);

it('stores nothing when an empty string is written to an empty field', function (string $type): void {
    blankableField(['name' => 'Subject', 'type' => $type]);
    $article = Article::create(['title' => 'A']);

    $article->setCustomField('subject', '');

    expect($article->customFieldValues()->count())->toBe(0)
        ->and($article->getCustomField('subject'))->toBeNull();
})->with(['number', 'boolean', 'date', 'decimal', 'text']);

it('clears a select value instead of rejecting an empty string as an option', function (): void {
    blankableField(['name' => 'Status', 'type' => 'select', 'options' => [
        ['key' => 'open', 'label' => 'Open', 'is_active' => true],
    ]]);
    $article = Article::create(['title' => 'A']);
    $article->setCustomField('status', 'open');

    $article->setCustomField('status', '');

    expect($article->customFieldValues()->count())->toBe(0)
        ->and($article->getCustomField('status'))->toBeNull();
});

it('refuses an empty string on a required field in a complete write', function (): void {
    blankableField(['name' => 'Rank', 'type' => 'number', 'is_required' => true]);
    $article = Article::create(['title' => 'A']);

    expect(fn () => $article->setCustomFields(['rank' => ''], complete: true))
        ->toThrow(ValidationException::class);
});

it('keeps the stored value of a required field refused as empty', function (): void {
    blankableField(['name' => 'Rank', 'type' => 'number', 'is_required' => true]);
    $article = Article::create(['title' => 'A']);
    $article->setCustomField('rank', 3);

    try {
        $article->setCustomFields(['rank' => ''], complete: true);
    } catch (ValidationException) {
    }

    expect($article->getCustomField('rank'))->toBe(3);
});

it('keeps a whitespace only string as a value', function (): void {
    blankableField(['name' => 'Note']);
    $article = Article::create(['title' => 'A']);

    $article->setCustomField('note', ' ');

    expect($article->getCustomField('note'))->toBe(' ');
});

it('clears an empty string next to the other values of the batch', function (): void {
    blankableField(['name' => 'Rank', 'type' => 'number']);
    blankableField(['name' => 'Note']);
    $article = Article::create(['title' => 'A']);
    $article->setCustomFields(['rank' => 5, 'note' => 'Keep']);

    $article->setCustomFields(['rank' => '', 'note' => 'Changed']);

    expect($article->getCustomFields())->toBe(['note' => 'Changed', 'rank' => null]);
});
