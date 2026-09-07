<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $fieldsTable = config('laravel-custom-fields.tables.fields');

        Schema::create(config('laravel-custom-fields.tables.values'), function (Blueprint $table) use ($fieldsTable): void {
            $keyType = config('laravel-custom-fields.key_type', 'id');

            if ($keyType === 'uuid') {
                $table->uuid('id')->primary();
                $table->foreignUuid('custom_field_id')->constrained($fieldsTable)->cascadeOnDelete();
            } elseif ($keyType === 'ulid') {
                $table->ulid('id')->primary();
                $table->foreignUlid('custom_field_id')->constrained($fieldsTable)->cascadeOnDelete();
            } else {
                $table->id();
                $table->foreignId('custom_field_id')->constrained($fieldsTable)->cascadeOnDelete();
            }

            match (config('laravel-custom-fields.morph_key_type', 'uuid')) {
                'uuid' => $table->uuidMorphs('valuable'),
                'ulid' => $table->ulidMorphs('valuable'),
                default => $table->morphs('valuable'),
            };

            $table->string('value_string')->nullable();
            $table->text('value_text')->nullable();
            $table->bigInteger('value_integer')->nullable();
            $table->decimal('value_decimal', 20, 6)->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->date('value_date')->nullable();
            $table->dateTime('value_datetime')->nullable();
            $table->json('value_json')->nullable();
            $table->timestamps();

            $table->unique(['custom_field_id', 'valuable_type', 'valuable_id'], 'cfv_field_valuable_unique');
            $table->index(['custom_field_id', 'value_string'], 'cfv_field_string_index');
            $table->index(['custom_field_id', 'value_boolean'], 'cfv_field_boolean_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('laravel-custom-fields.tables.values'));
    }
};
