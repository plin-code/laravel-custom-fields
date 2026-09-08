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

/**
 * Every driver names its column types differently, so a kind lists what SQLite,
 * MySQL and PostgreSQL each report for it. Asserting the kind keeps the test
 * about the key shape rather than about the vocabulary of one database.
 *
 * @return array<int, string>
 */
function columnTypesOf(string $kind): array
{
    return match ($kind) {
        'integer' => ['integer', 'bigint', 'int8'],
        'uuid' => ['varchar', 'char', 'uuid'],
        'ulid' => ['varchar', 'char', 'bpchar'],
    };
}

it('keeps integer keys on both tables by default', function (): void {
    CustomFields::registerEntity(Article::class, 'article');
    CustomField::create(['entity_type' => 'article', 'name' => 'Rank', 'type' => 'number']);
    $article = Article::create(['title' => 'A']);
    $article->setCustomField('rank', 5);

    expect(columnType('custom_fields', 'id'))->toBeIn(columnTypesOf('integer'))
        ->and(columnType('custom_field_values', 'valuable_id'))->toBeIn(columnTypesOf('integer'))
        ->and($article->customFieldValues()->first()?->getKey())->toBeInt()
        ->and((new CustomField)->getKeyType())->toBe('int')
        ->and((new CustomField)->getIncrementing())->toBeTrue();
});

it('stores the values of a uuid keyed host', function (): void {
    config()->set('laravel-custom-fields.key_type', 'uuid');
    config()->set('laravel-custom-fields.morph_key_type', 'uuid');
    $this->rebuildPackageTables();

    Schema::dropIfExists('uuid_documents');
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

    expect(columnType('custom_fields', 'id'))->toBeIn(columnTypesOf('uuid'))
        ->and(columnType('custom_field_values', 'valuable_id'))->toBeIn(columnTypesOf('uuid'))
        ->and(Str::isUuid((string) $field->getKey()))->toBeTrue()
        ->and(Str::isUuid((string) $row?->getKey()))->toBeTrue()
        ->and($row?->getAttribute('valuable_id'))->toBe($document->getKey())
        ->and($document->getCustomField('rank'))->toBe(7);
});

it('stores the values of a ulid keyed host', function (): void {
    config()->set('laravel-custom-fields.key_type', 'ulid');
    config()->set('laravel-custom-fields.morph_key_type', 'ulid');
    $this->rebuildPackageTables();

    Schema::dropIfExists('ulid_documents');
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

    expect(columnType('custom_fields', 'id'))->toBeIn(columnTypesOf('ulid'))
        ->and(columnType('custom_field_values', 'valuable_id'))->toBeIn(columnTypesOf('ulid'))
        ->and(Str::isUlid((string) $field->getKey()))->toBeTrue()
        ->and(Str::isUlid((string) $row?->getKey()))->toBeTrue()
        ->and($row?->getAttribute('valuable_id'))->toBe($document->getKey())
        ->and($document->getCustomField('rank'))->toBe(7)
        ->and((new CustomField)->getKeyType())->toBe('string')
        ->and((new CustomField)->getIncrementing())->toBeFalse();
});
