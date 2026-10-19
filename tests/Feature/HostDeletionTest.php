<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use PlinCode\CustomFields\Events\CustomFieldValueDeleted;
use PlinCode\CustomFields\Facades\CustomFields;
use PlinCode\CustomFields\Models\CustomField;
use PlinCode\CustomFields\Models\CustomFieldValue;
use Workbench\App\Models\Article;
use Workbench\App\Models\Note;

beforeEach(function (): void {
    CustomFields::registerEntity(Article::class, 'article');
    CustomFields::registerEntity(Note::class, 'note');

    Schema::dropIfExists('notes');
    Schema::create('notes', function (Blueprint $table): void {
        $table->id();
        $table->string('title');
        $table->timestamps();
        $table->softDeletes();
    });

    $this->beforeApplicationDestroyed(fn () => Schema::dropIfExists('notes'));

    CustomField::create(['entity_type' => 'article', 'name' => 'Subtitle', 'type' => 'text']);
    CustomField::create(['entity_type' => 'article', 'name' => 'Rank', 'type' => 'number']);
    CustomField::create(['entity_type' => 'note', 'name' => 'Subtitle', 'type' => 'text']);
});

it('deletes every stored value with a hard deleted host', function (): void {
    $article = Article::create(['title' => 'A']);
    $article->setCustomFields(['subtitle' => 'Sub', 'rank' => 3]);

    $article->delete();

    expect(CustomFieldValue::query()->count())->toBe(0);
});

it('dispatches one deleted event per removed value', function (): void {
    $article = Article::create(['title' => 'A']);
    $article->setCustomFields(['subtitle' => 'Sub', 'rank' => 3]);
    Event::fake([CustomFieldValueDeleted::class]);

    $article->delete();

    Event::assertDispatchedTimes(CustomFieldValueDeleted::class, 2);
});

it('keeps the stored values of a soft deleted host', function (): void {
    $note = Note::create(['title' => 'N']);
    $note->setCustomField('subtitle', 'Sub');
    Event::fake([CustomFieldValueDeleted::class]);

    $note->delete();

    expect($note->trashed())->toBeTrue()
        ->and(CustomFieldValue::query()->count())->toBe(1);
    Event::assertNotDispatched(CustomFieldValueDeleted::class);
});

it('deletes the stored values of a force deleted host', function (): void {
    $note = Note::create(['title' => 'N']);
    $note->setCustomField('subtitle', 'Sub');

    $note->forceDelete();

    expect(Note::withTrashed()->count())->toBe(0)
        ->and(CustomFieldValue::query()->count())->toBe(0);
});

it('deletes the stored values of a trashed host that is force deleted later', function (): void {
    $note = Note::create(['title' => 'N']);
    $note->setCustomField('subtitle', 'Sub');
    $note->delete();

    $note->forceDelete();

    expect(CustomFieldValue::query()->count())->toBe(0);
});

it('deletes a host without stored values', function (): void {
    $article = Article::create(['title' => 'A']);
    Event::fake([CustomFieldValueDeleted::class]);

    $article->delete();

    expect(Article::query()->count())->toBe(0);
    Event::assertNotDispatched(CustomFieldValueDeleted::class);
});

it('keeps the stored values of another host of the same class', function (): void {
    $first = Article::create(['title' => 'A']);
    $second = Article::create(['title' => 'B']);
    $first->setCustomField('subtitle', 'First');
    $second->setCustomField('subtitle', 'Second');

    $first->delete();

    expect(CustomFieldValue::query()->count())->toBe(1)
        ->and($second->getCustomField('subtitle'))->toBe('Second');
});
