<?php

declare(strict_types=1);

namespace PlinCode\CustomFields\Validation;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use PlinCode\CustomFields\Facades\CustomFields;

class ValueValidator
{
    /** @return array<string, array<int, mixed>> */
    public function rules(Model|string $model, bool $complete = false): array
    {
        $entityKey = CustomFields::entityKey($model);
        $fieldModel = CustomFields::fieldModel();
        $rules = [];

        foreach ($fieldModel::query()->where('entity_type', $entityKey)->where('is_active', true)->get() as $field) {
            $fieldRules = $field->fieldType()->rules($field);

            if ($complete && $field->is_required) {
                $fieldRules = array_values(array_diff($fieldRules, ['nullable']));
                array_unshift($fieldRules, 'required');
            }

            $rules[$field->slug] = $fieldRules;
        }

        return $rules;
    }

    /** @param array<string, mixed> $values */
    public function validate(Model $model, array $values, bool $complete = false): void
    {
        $validator = Validator::make($values, $this->rules($model, $complete));

        $validator->after(function (ValidatorContract $validator) use ($model, $values): void {
            foreach ($values as $slug => $value) {
                $field = $this->field($model, (string) $slug);

                if ($field === null || $field->getAttribute('type') !== 'multiselect' || ! is_array($value)) {
                    continue;
                }

                $keys = array_map(
                    static fn (array $option): string => (string) ($option['key'] ?? ''),
                    (array) $field->getAttribute('options'),
                );
                foreach ($value as $option) {
                    if (! in_array($option, $keys, true)) {
                        $validator->errors()->add($slug, "The selected option [{$option}] is invalid.");
                    }
                }
            }
        });

        $validator->validate();
    }

    private function field(Model $model, string $slug): ?Model
    {
        $fieldModel = CustomFields::fieldModel();

        return $fieldModel::query()->where('entity_type', CustomFields::entityKey($model))
            ->where('slug', $slug)->where('is_active', true)->first();
    }
}
