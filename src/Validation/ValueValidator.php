<?php

declare(strict_types=1);

namespace PlinCode\CustomFields\Validation;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use PlinCode\CustomFields\Facades\CustomFields;
use PlinCode\CustomFields\Models\CustomField;

class ValueValidator
{
    /**
     * Every definition of the entity, active and inactive, keyed by slug.
     *
     * @return array<string, Model>
     */
    public function definitions(Model|string $model): array
    {
        $fieldModel = CustomFields::fieldModel();

        /** @var array<string, Model> $definitions */
        $definitions = $fieldModel::query()
            ->where('entity_type', CustomFields::entityKey($model))
            ->get()
            ->keyBy(static fn (Model $field): string => (string) $field->getAttribute('slug'))
            ->all();

        return $definitions;
    }

    /**
     * @param  array<string, Model>|null  $definitions
     * @return array<string, array<int, mixed>>
     */
    public function rules(Model|string $model, bool $complete = false, ?array $definitions = null): array
    {
        $rules = [];

        foreach ($definitions ?? $this->definitions($model) as $slug => $field) {
            if (! $field->getAttribute('is_active')) {
                continue;
            }

            /** @var CustomField $field */
            $fieldRules = $field->fieldType()->rules($field);

            if ($complete && $field->getAttribute('is_required')) {
                $fieldRules = array_values(array_diff($fieldRules, ['nullable']));
                array_unshift($fieldRules, 'required');
            }

            $rules[$slug] = $fieldRules;
        }

        return $rules;
    }

    /**
     * Partial validation only inspects the submitted keys. Complete validation
     * inspects the state the model ends up with, so the values already stored
     * satisfy the required definitions the caller left out of the payload.
     *
     * @param  array<string, mixed>  $values
     * @param  array<string, Model>|null  $definitions  definitions keyed by slug, already loaded by the caller
     * @param  array<string, mixed>|null  $stored  current values keyed by slug, already loaded by the caller
     */
    public function validate(Model $model, array $values, bool $complete = false, ?array $definitions = null, ?array $stored = null): void
    {
        $definitions ??= $this->definitions($model);
        $rules = $this->rules($model, $complete, $definitions);
        $stored ??= $this->storedValues($model, $definitions);

        $data = $complete
            ? $values + array_intersect_key($stored, $rules)
            : $values;

        $validator = Validator::make($data, $rules, [], $this->attributeNames($definitions));

        $validator->after(function (ValidatorContract $validator) use ($values, $definitions, $stored): void {
            foreach ($values as $key => $value) {
                $slug = (string) $key;
                $field = $definitions[$slug] ?? null;

                if ($field === null) {
                    $validator->errors()->add($slug, $this->message('unknown_field', ['attribute' => $slug]));

                    continue;
                }

                if (! $field->getAttribute('is_active')) {
                    $validator->errors()->add($slug, $this->message('inactive_field', [
                        'attribute' => (string) $field->getAttribute('name'),
                    ]));

                    continue;
                }

                $this->validateOptions($validator, $field, $slug, $value, $stored[$slug] ?? null);
            }
        });

        $validator->validate();
    }

    /**
     * An option can be kept by the record that already holds it, while a new
     * assignment is limited to the options that are still active.
     */
    private function validateOptions(ValidatorContract $validator, Model $field, string $slug, mixed $value, mixed $current): void
    {
        if ($value === null || ! in_array($field->getAttribute('type'), ['select', 'multiselect'], true)) {
            return;
        }

        /** @var CustomField $field */
        $keys = $field->optionKeys();
        $activeKeys = $field->activeOptionKeys();
        $submitted = $field->getAttribute('type') === 'multiselect' ? array_values((array) $value) : [$value];

        foreach ($submitted as $option) {
            $isCurrent = is_array($current) ? in_array($option, $current, true) : $current === $option;

            if (! in_array($option, $keys, true) || (! in_array($option, $activeKeys, true) && ! $isCurrent)) {
                $validator->errors()->add($slug, $this->message('invalid_option', [
                    'option' => is_scalar($option) ? (string) $option : gettype($option),
                    'attribute' => (string) $field->getAttribute('name'),
                ]));
            }
        }
    }

    /**
     * The values already stored for the model, keyed by slug.
     *
     * @param  array<string, Model>  $definitions
     * @return array<string, mixed>
     */
    private function storedValues(Model $model, array $definitions): array
    {
        if ($model->getKey() === null) {
            return [];
        }

        $slugs = [];

        foreach ($definitions as $slug => $field) {
            $slugs[(string) $field->getKey()] = $slug;
        }

        $valueModel = CustomFields::valueModel();
        $stored = [];

        foreach ($valueModel::query()
            ->where('valuable_type', $model->getMorphClass())
            ->where('valuable_id', $model->getKey())
            ->get() as $row) {
            $slug = $slugs[(string) $row->getAttribute('custom_field_id')] ?? null;

            if ($slug === null) {
                continue;
            }

            $row->setRelation('customField', $definitions[$slug]);
            $stored[$slug] = $row->getValue();
        }

        return $stored;
    }

    /**
     * @param  array<string, Model>  $definitions
     * @return array<string, string>
     */
    private function attributeNames(array $definitions): array
    {
        $attributes = [];

        foreach ($definitions as $slug => $field) {
            $attributes[$slug] = (string) $field->getAttribute('name');
        }

        return $attributes;
    }

    /** @param array<string, string> $replace */
    private function message(string $key, array $replace): string
    {
        return (string) trans('laravel-custom-fields::messages.validation.'.$key, $replace);
    }
}
