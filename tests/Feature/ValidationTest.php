<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use PlinCode\CustomFields\Facades\CustomFields;
use Workbench\App\CustomFields\RatingType;
use Workbench\App\Models\Article;

beforeEach(function (): void {
    CustomFields::registerEntity(Article::class, 'article');
});

/** @return array<string, array<int, string>> */
function errorsOf(Closure $callback): array
{
    try {
        $callback();
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    return [];
}

it('accepts a partial write that leaves a required field out', function (): void {
    articleField(['name' => 'Email', 'type' => 'email', 'is_required' => true]);
    articleField(['name' => 'Rank', 'type' => 'number']);
    $article = Article::create(['title' => 'A']);

    $article->setCustomField('rank', 3);

    expect($article->getCustomField('rank'))->toBe(3);
});

it('accepts a complete write when the required field is already stored', function (): void {
    articleField(['name' => 'Email', 'type' => 'email', 'is_required' => true]);
    articleField(['name' => 'Rank', 'type' => 'number']);
    $article = Article::create(['title' => 'A']);
    $article->setCustomField('email', 'person@example.com');

    $article->setCustomFields(['rank' => 3], complete: true);

    expect($article->getCustomFields())->toBe([
        'email' => 'person@example.com',
        'rank' => 3,
    ]);
});

it('refuses a complete write that clears a required field', function (): void {
    articleField(['name' => 'Email', 'type' => 'email', 'is_required' => true]);
    $article = Article::create(['title' => 'A']);
    $article->setCustomField('email', 'person@example.com');

    expect(errorsOf(fn () => $article->setCustomFields(['email' => null], complete: true)))
        ->toBe(['email' => ['The email field is required.']])
        ->and($article->getCustomField('email'))->toBe('person@example.com');
});

it('keeps a stored value out of the input rules of its own type', function (): void {
    CustomFields::registerType(RatingType::class);
    articleField(['name' => 'Score', 'type' => 'rating']);
    articleField(['name' => 'Rank', 'type' => 'number']);
    $article = Article::create(['title' => 'A']);
    $article->setCustomField('score', 4);

    $article->setCustomFields(['rank' => 3], complete: true);

    expect($article->getCustomField('score'))->toBe('4 stars')
        ->and($article->getCustomField('rank'))->toBe(3);
});

it('satisfies a required field of a type that reads back another shape', function (): void {
    CustomFields::registerType(RatingType::class);
    articleField(['name' => 'Score', 'type' => 'rating', 'is_required' => true]);
    articleField(['name' => 'Rank', 'type' => 'number']);
    $article = Article::create(['title' => 'A']);
    $article->setCustomField('score', 4);

    $article->setCustomFields(['rank' => 3], complete: true);

    expect(errorsOf(fn () => Article::create(['title' => 'B'])->setCustomFields(['rank' => 3], complete: true)))
        ->toBe(['score' => ['The score field is required.']]);
});

it('refuses a complete write when a required field was never stored', function (): void {
    articleField(['name' => 'Email', 'type' => 'email', 'is_required' => true]);
    articleField(['name' => 'Rank', 'type' => 'number']);
    $article = Article::create(['title' => 'A']);

    expect(errorsOf(fn () => $article->setCustomFields(['rank' => 3], complete: true)))
        ->toBe(['email' => ['The email field is required.']])
        ->and($article->customFieldValues()->count())->toBe(0);
});

it('validates a custom value before writing it', function (): void {
    articleField(['name' => 'Rank', 'type' => 'number']);
    $article = Article::create(['title' => 'A']);

    expect(fn () => $article->setCustomField('rank', 'invalid'))
        ->toThrow(ValidationException::class);
});

it('names the field rather than the slug in an error message', function (): void {
    articleField(['name' => 'Data Di Nascita', 'type' => 'date', 'is_required' => true]);
    $article = Article::create(['title' => 'A']);

    expect(errorsOf(fn () => $article->setCustomFields([], complete: true)))
        ->toBe(['data-di-nascita' => ['The data di nascita field is required.']]);
});

it('keeps an inactive selected option readable but blocks new assignments', function (): void {
    $field = articleField(['name' => 'Status', 'type' => 'select', 'options' => [
        ['key' => 'legacy', 'label' => 'Legacy', 'is_active' => true],
        ['key' => 'current', 'label' => 'Current', 'is_active' => true],
    ]]);
    $article = Article::create(['title' => 'A']);
    $article->setCustomField('status', 'legacy');

    $field->update(['options' => [
        ['key' => 'legacy', 'label' => 'Legacy', 'is_active' => false],
        ['key' => 'current', 'label' => 'Current', 'is_active' => true],
    ]]);

    expect($article->getCustomField('status'))->toBe('legacy')
        ->and(errorsOf(fn () => Article::create(['title' => 'B'])->setCustomField('status', 'legacy')))
        ->toBe(['status' => ['The selected option legacy is invalid for status.']]);
});

it('reports an inactive option in the locale of the request', function (): void {
    $field = articleField(['name' => 'Status', 'type' => 'select', 'options' => [
        ['key' => 'legacy', 'label' => 'Legacy', 'is_active' => true],
        ['key' => 'current', 'label' => 'Current', 'is_active' => true],
    ]]);
    $field->update(['options' => [
        ['key' => 'legacy', 'label' => 'Legacy', 'is_active' => false],
        ['key' => 'current', 'label' => 'Current', 'is_active' => true],
    ]]);
    $article = Article::create(['title' => 'A']);

    app()->setLocale('it');

    expect(errorsOf(fn () => $article->setCustomField('status', 'legacy')))
        ->toBe(['status' => ['L’opzione selezionata legacy non è valida per status.']]);
});

it('blocks a new assignment of an inactive option inside a multiselect', function (): void {
    $field = articleField(['name' => 'Tags', 'type' => 'multiselect', 'options' => [
        ['key' => 'legacy', 'label' => 'Legacy', 'is_active' => true],
        ['key' => 'current', 'label' => 'Current', 'is_active' => true],
    ]]);
    $holder = Article::create(['title' => 'A']);
    $holder->setCustomField('tags', ['legacy']);
    $field->update(['options' => [
        ['key' => 'legacy', 'label' => 'Legacy', 'is_active' => false],
        ['key' => 'current', 'label' => 'Current', 'is_active' => true],
    ]]);

    expect($holder->getCustomField('tags'))->toBe(['legacy']);

    $holder->setCustomField('tags', ['legacy', 'current']);

    expect($holder->getCustomField('tags'))->toBe(['legacy', 'current'])
        ->and(errorsOf(fn () => Article::create(['title' => 'B'])->setCustomField('tags', ['legacy'])))
        ->toBe(['tags' => ['The selected option legacy is invalid for tags.']]);
});

it('refuses an option key that was never defined', function (): void {
    articleField(['name' => 'Status', 'type' => 'select', 'options' => [
        ['key' => 'current', 'label' => 'Current', 'is_active' => true],
    ]]);
    $article = Article::create(['title' => 'A']);

    expect(errorsOf(fn () => $article->setCustomField('status', 'ghost')))
        ->toHaveKey('status');
});

it('validates a payload through the manager without preloaded state', function (): void {
    articleField(['name' => 'Email', 'type' => 'email', 'is_required' => true]);
    articleField(['name' => 'Rank', 'type' => 'number']);
    $article = Article::create(['title' => 'A']);
    $article->setCustomField('email', 'person@example.com');

    CustomFields::validate($article, ['rank' => 3], complete: true);

    expect(errorsOf(fn () => CustomFields::validate($article, ['rank' => 'nope'])))
        ->toHaveKey('rank');
});

it('builds rules for the active definitions only', function (): void {
    articleField(['name' => 'Rank', 'type' => 'number']);
    articleField(['name' => 'Legacy', 'type' => 'text', 'is_active' => false]);
    $validator = CustomFields::validator();

    expect(array_keys($validator->rules(Article::class)))->toBe(['rank'])
        ->and(array_keys($validator->definitions(Article::class)))->toEqualCanonicalizing(['rank', 'legacy']);
});

it('adds the required rule only to a complete validation', function (): void {
    articleField(['name' => 'Email', 'type' => 'email', 'is_required' => true]);
    $validator = CustomFields::validator();

    expect($validator->rules(Article::class))->toBe(['email' => ['nullable', 'email', 'max:255']])
        ->and($validator->rules(Article::class, complete: true))->toBe(['email' => ['required', 'email', 'max:255']]);
});
