<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array INDEXES = [
        'cfv_field_integer_index' => 'value_integer',
        'cfv_field_decimal_index' => 'value_decimal',
        'cfv_field_date_index' => 'value_date',
        'cfv_field_datetime_index' => 'value_datetime',
    ];

    public function up(): void
    {
        $tableName = config('laravel-custom-fields.tables.values');

        foreach (self::INDEXES as $name => $column) {
            if (Schema::hasIndex($tableName, $name)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($name, $column): void {
                $table->index(['custom_field_id', $column], $name);
            });
        }
    }
};
