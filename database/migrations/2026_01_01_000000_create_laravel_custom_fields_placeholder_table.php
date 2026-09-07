<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('laravel-custom-fields.tables.fields'), function (Blueprint $table): void {
            $keyType = config('laravel-custom-fields.key_type', 'id');

            if ($keyType === 'uuid') {
                $table->uuid('id')->primary();
            } elseif ($keyType === 'ulid') {
                $table->ulid('id')->primary();
            } else {
                $table->id();
            }

            $table->string('entity_type');
            $table->string('name');
            $table->string('slug', 100);
            $table->string('type', 50);
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['entity_type', 'name']);
            $table->unique(['entity_type', 'slug']);
            $table->index(['entity_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('laravel-custom-fields.tables.fields'));
    }
};
