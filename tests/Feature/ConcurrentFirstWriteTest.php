<?php

declare(strict_types=1);

use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PlinCode\CustomFields\Facades\CustomFields;
use PlinCode\CustomFields\Models\CustomField;
use PlinCode\CustomFields\Models\CustomFieldValue;
use Workbench\App\Models\Article;

beforeEach(function (): void {
    CustomFields::registerEntity(Article::class, 'article');
});

/**
 * Stores a competing first row of the field as soon as the write transaction
 * opens, which is after the trait read the rows and before it inserts its own.
 */
function competingFirstRow(CustomField $field, Article $article, string $value): void
{
    $inserted = false;

    Event::listen(TransactionBeginning::class, function () use (&$inserted, $field, $article, $value): void {
        if ($inserted) {
            return;
        }

        $inserted = true;

        DB::table('custom_field_values')->insert([
            'custom_field_id' => $field->getKey(),
            'valuable_type' => $article->getMorphClass(),
            'valuable_id' => $article->getKey(),
            'value_string' => $value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });
}

it('keeps the later write when two requests store the first value of a field at the same time', function (): void {
    $field = CustomField::create(['name' => 'Notes', 'entity_type' => 'article', 'type' => 'text']);
    $article = Article::create(['title' => 'A']);
    competingFirstRow($field, $article, 'first');

    $article->setCustomField('notes', 'second');

    expect($article->getCustomField('notes'))->toBe('second')
        ->and(CustomFieldValue::query()->count())->toBe(1);
});

it('still writes the other fields of the batch when one of them lost the race', function (): void {
    $notes = CustomField::create(['name' => 'Notes', 'entity_type' => 'article', 'type' => 'text']);
    CustomField::create(['name' => 'Age', 'entity_type' => 'article', 'type' => 'number']);
    $article = Article::create(['title' => 'A']);
    competingFirstRow($notes, $article, 'first');

    $article->setCustomFields(['notes' => 'second', 'age' => 3]);

    expect($article->getCustomFields())->toBe(['age' => 3, 'notes' => 'second'])
        ->and(CustomFieldValue::query()->count())->toBe(2);
});
