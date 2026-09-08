<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PlinCode\CustomFields\Facades\CustomFields;
use PlinCode\CustomFields\Models\CustomField;
use Workbench\App\Models\Article;
use Workbench\App\Models\UlidDocument;
use Workbench\App\Models\UuidDocument;

/** The type reported by the schema for one column of a package table. */
function columnType(string $table, string $column): string
{
    $found = collect(Schema::getColumns($table))->firstWhere('name', $column);

    return is_array($found) ? (string) $found['type_name'] : '';
}

it('keeps integer keys on both tables by default', function (): void {
    CustomFields::registerEntity(Article::class, 'article');
    CustomField::create(['entity_type' => 'article', 'name' => 'Rank', 'type' => 'number']);
    $article = Article::create(['title' => 'A']);
    $article->setCustomField('rank', 5);

    expect(columnType('custom_fields', 'id'))->toBe('integer')
        ->and(columnType('custom_field_values', 'valuable_id'))->toBe('integer')
        ->and($article->customFieldValues()->first()?->getKey())->toBeInt()
        ->and((new CustomField)->getKeyType())->toBe('int')
        ->and((new CustomField)->getIncrementing())->toBeTrue();
});

it('stores the values of a uuid keyed host', function (): void {
    config()->set('laravel-custom-fields.key_type', 'uuid');
    config()->set('laravel-custom-fields.morph_key_type', 'uuid');
    $this->rebuildPackageTables();

    Schema::create('uuid_documents', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('title');
        $table->timestamps();
    });

    CustomFields::registerEntity(UuidDocument::class, 'uuid-document');
    $field = CustomField::create(['entity_type' => 'uuid-document', 'name' => 'Rank', 'type' => 'number']);
    $document = UuidDocument::create(['title' => 'A']);
    $document->setCustomField('rank', 7);
    $row = $document->customFieldValues()->first();

    expect(columnType('custom_fields', 'id'))->toBe('varchar')
        ->and(columnType('custom_field_values', 'valuable_id'))->toBe('varchar')
        ->and(Str::isUuid((string) $field->getKey()))->toBeTrue()
        ->and(Str::isUuid((string) $row?->getKey()))->toBeTrue()
        ->and($row?->getAttribute('valuable_id'))->toBe($document->getKey())
        ->and($document->getCustomField('rank'))->toBe(7);
});

it('stores the values of a ulid keyed host', function (): void {
    config()->set('laravel-custom-fields.key_type', 'ulid');
    config()->set('laravel-custom-fields.morph_key_type', 'ulid');
    $this->rebuildPackageTables();

    Schema::create('ulid_documents', function (Blueprint $table): void {
        $table->ulid('id')->primary();
        $table->string('title');
        $table->timestamps();
    });

    CustomFields::registerEntity(UlidDocument::class, 'ulid-document');
    $field = CustomField::create(['entity_type' => 'ulid-document', 'name' => 'Rank', 'type' => 'number']);
    $document = UlidDocument::create(['title' => 'A']);
    $document->setCustomField('rank', 7);
    $row = $document->customFieldValues()->first();

    expect(columnType('custom_fields', 'id'))->toBe('varchar')
        ->and(columnType('custom_field_values', 'valuable_id'))->toBe('varchar')
        ->and(Str::isUlid((string) $field->getKey()))->toBeTrue()
        ->and(Str::isUlid((string) $row?->getKey()))->toBeTrue()
        ->and($row?->getAttribute('valuable_id'))->toBe($document->getKey())
        ->and($document->getCustomField('rank'))->toBe(7)
        ->and((new CustomField)->getKeyType())->toBe('string')
        ->and((new CustomField)->getIncrementing())->toBeFalse();
});
