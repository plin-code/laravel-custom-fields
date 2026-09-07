<?php

declare(strict_types=1);

namespace PlinCode\CustomFields\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PlinCode\CustomFields\Facades\CustomFields;

class CustomFieldObserver
{
    public function creating(Model $field): void
    {
        $name = trim(mb_strtolower((string) $field->getAttribute('name')));

        if ($name === '') {
            throw new InvalidArgumentException('A custom field name cannot be empty.');
        }

        $field->setAttribute('name', $name);
        $field->setAttribute('slug', $field->getAttribute('slug') ?: $this->slug($name, (string) $field->getAttribute('entity_type')));
    }

    private function slug(string $name, string $entityType): string
    {
        $base = Str::slug($name) ?: 'field';
        $base = mb_substr($base, 0, 100);
        $slug = $base;
        $suffix = 2;
        $model = CustomFields::fieldModel();

        while ($model::query()->where('entity_type', $entityType)->where('slug', $slug)->exists()) {
            $suffixText = '-'.$suffix++;
            $slug = mb_substr($base, 0, 100 - mb_strlen($suffixText)).$suffixText;
        }

        return $slug;
    }
}
