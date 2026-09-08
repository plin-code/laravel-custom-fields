<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use PlinCode\CustomFields\Events\CustomFieldValueDeleted;
use PlinCode\CustomFields\Events\CustomFieldValueSaved;
use PlinCode\CustomFields\Exceptions\ModelNotPersistedException;
use PlinCode\CustomFields\Exceptions\UnknownCustomFieldException;
use PlinCode\CustomFields\Facades\CustomFields;
use PlinCode\CustomFields\Models\CustomField;
use PlinCode\CustomFields\Models\CustomFieldValue;
use Workbench\App\Models\Article;

beforeEach(function (): void {
    CustomFields::registerEntity(Article::class, 'article');
});

/** @param array<string, mixed> $attributes */
function field(array $attributes): CustomField
{
    return CustomField::create($attributes + ['entity_type' => 'article', 'type' => 'text']);
}

it('writes and reads a typed custom value', function (): void {
    field(['name' => 'Rank', 'type' => 'number']);
    $article = Article::create(['title' => 'A']);

    $article->setCustomField('rank', 10);

    expect($article->getCustomField('rank'))->toBe(10);
});

it('writes a batch of values atomically', function (): void {
    field(['name' => 'Rank', 'type' => 'number']);
    field(['name' => 'Email', 'type' => 'email', 'is_required' => true]);
    $article = Article::create(['title' => 'A']);

    $article->setCustomFields([
        'rank' => 10,
        'email' => 'person@example.com',
    ], complete: true);

    expect($article->getCustomFields())->toBe([
        'email' => 'person@example.com',
        'rank' => 10,
    ]);
});

it('writes nothing when one value of the batch is invalid', function (): void {
    field(['name' => 'Rank', 'type' => 'number']);
    field(['name' => 'Email', 'type' => 'email']);
    $article = Article::create(['title' => 'A']);

    expect(fn () => $article->setCustomFields(['rank' => 20, 'email' => 'invalid']))
        ->toThrow(ValidationException::class)
        ->and($article->customFieldValues()->count())->toBe(0);
});

it('refuses a host that was never persisted before touching the database', function (): void {
    field(['name' => 'Rank', 'type' => 'number']);
    $article = new Article(['title' => 'A']);

    DB::enableQueryLog();

    expect(fn () => $article->setCustomField('rank', 5))
        ->toThrow(ModelNotPersistedException::class, 'Custom field values require a persisted ['.Article::class.'] instance.')
        ->and(DB::getQueryLog())->toBe([]);
});

it('names the model in the error of an unknown slug', function (): void {
    $article = Article::create(['title' => 'A']);

    expect(fn (): mixed => $article->getCustomField('rank'))
        ->toThrow(UnknownCustomFieldException::class, 'Custom field [rank] is not defined for model ['.Article::class.'].')
        ->and(fn (): mixed => $article->getCustomField('rank'))->toThrow(InvalidArgumentException::class);
});

it('reports an unknown slug of a batch as a validation error', function (): void {
    field(['name' => 'Rank', 'type' => 'number']);
    $article = Article::create(['title' => 'A']);

    try {
        $article->setCustomFields(['rank' => 1, 'ghost' => 'x']);
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe(['ghost' => ['The ghost field is not defined for this model.']]);
    }

    expect($article->customFieldValues()->count())->toBe(0);
});

it('removes the stored row when a value is set to null', function (string $type, mixed $value, mixed $default): void {
    field(['name' => 'Thing', 'type' => $type, 'options' => [
        ['key' => 'a', 'label' => 'A', 'is_active' => true],
    ]]);
    $article = Article::create(['title' => 'A']);
    $article->setCustomField('thing', $value);

    expect($article->customFieldValues()->count())->toBe(1);

    $article->setCustomField('thing', null);

    expect($article->customFieldValues()->count())->toBe(0)
        ->and($article->getCustomField('thing'))->toBe($default);
})->with([
    'number' => ['number', 7, null],
    'select' => ['select', 'a', null],
    'multiselect' => ['multiselect', ['a'], []],
]);

it('keeps the row of a multiselect cleared to an empty selection', function (): void {
    field(['name' => 'Tags', 'type' => 'multiselect', 'options' => [
        ['key' => 'a', 'label' => 'A', 'is_active' => true],
    ]]);
    $article = Article::create(['title' => 'A']);
    $article->setCustomField('tags', ['a']);

    $article->setCustomField('tags', []);

    expect($article->customFieldValues()->count())->toBe(1)
        ->and($article->getCustomField('tags'))->toBe([]);
});

it('dispatches the value events on a write, a clear and a null', function (): void {
    field(['name' => 'Rank', 'type' => 'number']);
    field(['name' => 'Notes', 'type' => 'text']);
    $article = Article::create(['title' => 'A']);

    Event::fake([CustomFieldValueSaved::class, CustomFieldValueDeleted::class]);

    $article->setCustomFields(['rank' => 1, 'notes' => 'x']);
    $article->clearCustomField('rank');
    $article->setCustomField('notes', null);

    Event::assertDispatchedTimes(CustomFieldValueSaved::class, 2);
    Event::assertDispatchedTimes(CustomFieldValueDeleted::class, 2);
});

it('reads a deactivated definition only when the caller asks for it', function (): void {
    $field = field(['name' => 'Legacy', 'type' => 'text']);
    $article = Article::create(['title' => 'A']);
    $article->setCustomField('legacy', 'kept');

    $field->update(['is_active' => false]);

    expect($article->getCustomField('legacy', includeInactive: true))->toBe('kept')
        ->and($article->getCustomFields())->toBe([])
        ->and($article->getCustomFields(includeInactive: true))->toBe(['legacy' => 'kept'])
        ->and(fn (): mixed => $article->getCustomField('legacy'))
        ->toThrow(UnknownCustomFieldException::class, 'Custom field [legacy] is not active for model ['.Article::class.'].');
});

it('clears the value of a deactivated definition without a flag', function (): void {
    $field = field(['name' => 'Legacy', 'type' => 'text']);
    $article = Article::create(['title' => 'A']);
    $article->setCustomField('legacy', 'kept');
    $field->update(['is_active' => false]);

    $article->clearCustomField('legacy');

    expect($article->customFieldValues()->count())->toBe(0);
});

it('refuses to write to a deactivated definition', function (): void {
    $field = field(['name' => 'Legacy', 'type' => 'text']);
    $article = Article::create(['title' => 'A']);
    $field->update(['is_active' => false]);

    try {
        $article->setCustomField('legacy', 'new');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe(['legacy' => ['The legacy field is not active.']]);
    }

    expect($article->customFieldValues()->count())->toBe(0);
});

it('serves a fresh relation after a write on the same instance', function (): void {
    field(['name' => 'Rank', 'type' => 'number']);
    $article = Article::create(['title' => 'A']);
    $article->load('customFieldValues');

    $article->setCustomField('rank', 3);

    expect($article->customFieldValues)->toHaveCount(1)
        ->and($article->getCustomField('rank'))->toBe(3);
});

it('filters hosts on a stored value through the model scope', function (): void {
    field(['name' => 'Rank', 'type' => 'number']);
    $first = Article::create(['title' => 'First']);
    $second = Article::create(['title' => 'Second']);
    $first->setCustomField('rank', 10);
    $second->setCustomField('rank', 2);

    expect(Article::whereCustomField('rank', 10)->pluck('title')->all())->toBe(['First']);
});

it('writes a batch of three slugs in a constant number of queries', function (): void {
    field(['name' => 'Rank', 'type' => 'number']);
    field(['name' => 'Email', 'type' => 'email']);
    field(['name' => 'Notes', 'type' => 'text']);
    $article = Article::create(['title' => 'A']);

    DB::enableQueryLog();
    $article->setCustomFields(['rank' => 1, 'email' => 'a@example.com', 'notes' => 'x']);
    $insert = count(DB::getQueryLog());

    DB::flushQueryLog();
    $article->setCustomFields(['rank' => 2, 'email' => 'b@example.com', 'notes' => 'y']);
    $update = count(DB::getQueryLog());

    // One query for the definitions, one for the stored rows, one per written slug.
    expect($insert)->toBe(5)
        ->and($update)->toBe(5);
});

it('reads every value of a host in two queries', function (): void {
    field(['name' => 'Rank', 'type' => 'number']);
    field(['name' => 'Notes', 'type' => 'text']);
    $article = Article::create(['title' => 'A']);
    $article->setCustomFields(['rank' => 1, 'notes' => 'x']);

    DB::enableQueryLog();
    $article->getCustomFields();
    $all = count(DB::getQueryLog());

    DB::flushQueryLog();
    $article->getCustomField('rank');
    $one = count(DB::getQueryLog());

    expect($all)->toBe(2)->and($one)->toBe(2);
});

it('ignores a value row that belongs to another host', function (): void {
    $field = field(['name' => 'Rank', 'type' => 'number']);
    $first = Article::create(['title' => 'First']);
    $second = Article::create(['title' => 'Second']);
    $first->setCustomField('rank', 10);

    CustomFieldValue::create([
        'custom_field_id' => $field->getKey(),
        'valuable_type' => 'article',
        'valuable_id' => $second->getKey(),
        'value_integer' => 2,
    ]);

    expect($first->getCustomField('rank'))->toBe(10)
        ->and($second->getCustomField('rank'))->toBe(2);
});
