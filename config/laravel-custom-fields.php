<?php

declare(strict_types=1);

use PlinCode\CustomFields\Models\CustomField;
use PlinCode\CustomFields\Models\CustomFieldValue;

return [

    /*
     | The database tables the package creates and reads.
     |
     | "fields" holds the field definitions, "values" holds one row per field
     | per model instance. Rename them to fit your own naming convention, then
     | publish and run the migrations. Changing a name once the tables exist
     | requires renaming the tables yourself.
     */
    'tables' => [
        'fields' => 'custom_fields',
        'values' => 'custom_field_values',
    ],

    /*
     | The primary key type of both package tables.
     |
     | Supported values: "id" for auto incrementing big integers, "uuid", "ulid".
     | The foreign key on the values table follows the same type.
     |
     | This must be set BEFORE you run the migrations. It cannot be changed
     | afterwards without writing your own data migration.
     */
    'key_type' => 'id',

    /*
     | The key type of the models that own custom fields, used for the
     | valuable_id column of the polymorphic relation.
     |
     | Supported values: "id" for auto incrementing big integers, "uuid", "ulid".
     | Keep "id" for a default Laravel installation. Switch to "uuid" or "ulid"
     | only when every model you attach custom fields to uses that key type.
     |
     | This must be set BEFORE you run the migrations. It cannot be changed
     | afterwards without writing your own data migration.
     */
    'morph_key_type' => 'id',

    /*
     | The Eloquent models the package resolves at runtime.
     |
     | Point these at your own subclasses to add relations, casts, a global
     | scope or a dedicated database connection. Each replacement must extend
     | the package model it replaces.
     */
    'models' => [
        'custom_field' => CustomField::class,
        'custom_field_value' => CustomFieldValue::class,
    ],

    /*
     | The prefix applied to the generated spatie/laravel-query-builder filter
     | and sort names.
     |
     | With the default prefix a field with the slug "release_date" is exposed as
     | filter[cf_release_date] and sort=cf_release_date. Change it to avoid a
     | clash with the native columns you already expose.
     */
    'key_prefix' => 'cf_',

];
