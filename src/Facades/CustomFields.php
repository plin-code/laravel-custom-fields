<?php

declare(strict_types=1);

namespace PlinCode\CustomFields\Facades;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use PlinCode\CustomFields\Contracts\FieldType;
use PlinCode\CustomFields\Query\CustomFieldFilter;
use PlinCode\CustomFields\Validation\ValueValidator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

/**
 * @method static void registerType(string $type)
 * @method static FieldType type(string $key)
 * @method static array<string, FieldType> types()
 * @method static void registerEntity(class-string<Model> $model, string $key, string|null $label = null)
 * @method static array<string, array{key: string, label: string}> entities()
 * @method static string entityKey(Model|string $model)
 * @method static string fieldModel()
 * @method static string valueModel()
 * @method static ValueValidator validator()
 * @method static void validate(Model $model, array<string, mixed> $values, bool $complete = false)
 * @method static array<int, AllowedFilter> filtersFor(Model|string $model)
 * @method static array<int, AllowedSort> sortsFor(Model|string $model)
 * @method static array{filters: array<int, AllowedFilter>, sorts: array<int, AllowedSort>} queryOptionsFor(Model|string $model)
 * @method static string keyPrefix()
 * @method static string filterName(string $slug, string $operation = CustomFieldFilter::EQUALS)
 * @method static string sortName(string $slug)
 *
 * @see \PlinCode\CustomFields\CustomFields
 */
class CustomFields extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \PlinCode\CustomFields\CustomFields::class;
    }
}
