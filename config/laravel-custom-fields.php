<?php

declare(strict_types=1);
use PlinCode\CustomFields\Models\CustomField;
use PlinCode\CustomFields\Models\CustomFieldValue;

return [

    'tables' => [
        'fields' => 'custom_fields',
        'values' => 'custom_field_values',
    ],

    'key_type' => 'id',
    'morph_key_type' => 'uuid',

    'models' => [
        'custom_field' => CustomField::class,
        'custom_field_value' => CustomFieldValue::class,
    ],

    'key_prefix' => 'cf_',

];
