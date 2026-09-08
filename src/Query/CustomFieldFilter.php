<?php

declare(strict_types=1);

namespace PlinCode\CustomFields\Query;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Expression;
use InvalidArgumentException;
use PlinCode\CustomFields\Contracts\FieldType;
use PlinCode\CustomFields\Models\CustomField;
use Spatie\QueryBuilder\Exceptions\InvalidFilterValue;
use Spatie\QueryBuilder\Filters\Filter;

/**
 * Applies a single declared query operation of a custom field.
 *
 * The operation always comes from the field type registry, never from the request,
 * and a field type that does not declare the operation is refused at construction.
 *
 * @implements Filter<Model>
 */
class CustomFieldFilter implements Filter
{
    public const string EQUALS = 'equals';

    public const string IN = 'in';

    public const string CONTAINS = 'contains';

    public const string GREATER_THAN = 'greater_than';

    public const string LESS_THAN = 'less_than';

    public const string BETWEEN = 'between';

    public const string IS_NULL = 'is_null';

    public const string IS_NOT_NULL = 'is_not_null';

    public const string CONTAINS_ANY = 'contains_any';

    public const string CONTAINS_ALL = 'contains_all';

    /**
     * Escape character for the contains operation.
     *
     * A backslash is not usable here because MySQL, PostgreSQL and SQLite disagree on
     * how a backslash survives a string literal, so a neutral character is used and the
     * escape clause is always written explicitly.
     */
    private const string LIKE_ESCAPE = '!';

    private readonly string $operation;

    public function __construct(private readonly Model $field, string $operation = self::EQUALS)
    {
        if (! self::supports($operation)) {
            throw new InvalidArgumentException("Custom field query operation [{$operation}] is not supported.");
        }

        if (! in_array($operation, $this->fieldType()->queryOperations(), true)) {
            throw new InvalidArgumentException(sprintf(
                'Custom field [%s] does not declare the query operation [%s].',
                (string) $this->field->getAttribute('slug'),
                $operation,
            ));
        }

        $this->operation = $operation;
    }

    /**
     * Operations this filter can apply.
     *
     * @return array<int, string>
     */
    public static function operations(): array
    {
        return [
            self::EQUALS,
            self::IN,
            self::CONTAINS,
            self::GREATER_THAN,
            self::LESS_THAN,
            self::BETWEEN,
            self::IS_NULL,
            self::IS_NOT_NULL,
            self::CONTAINS_ANY,
            self::CONTAINS_ALL,
        ];
    }

    public static function supports(string $operation): bool
    {
        return in_array($operation, self::operations(), true);
    }

    public function operation(): string
    {
        return $this->operation;
    }

    public function __invoke(Builder $query, mixed $value, string $property): void
    {
        if ($this->operation === self::IS_NULL) {
            $this->applyPresence($query, ! $this->flag($value));

            return;
        }

        if ($this->operation === self::IS_NOT_NULL) {
            $this->applyPresence($query, $this->flag($value));

            return;
        }

        $query->whereHas('customFieldValues', function (Builder $inner) use ($value): void {
            $this->applyValue($inner, $value);
        });
    }

    /** @param  Builder<Model>  $inner */
    private function applyValue(Builder $inner, mixed $value): void
    {
        $values = $inner->getModel();
        $column = $values->qualifyColumn($this->fieldType()->storageColumn());

        $inner->where($values->qualifyColumn('custom_field_id'), $this->field->getKey());

        match ($this->operation) {
            self::EQUALS => is_array($value)
                ? $inner->whereIn($column, $this->castMany($value))
                : $inner->where($column, $this->cast($value)),
            self::IN => $inner->whereIn($column, $this->castMany($this->many($value))),
            self::CONTAINS => $this->applyContains($inner, $column, $value),
            self::GREATER_THAN => $inner->where($column, '>', $this->cast($this->single($value))),
            self::LESS_THAN => $inner->where($column, '<', $this->cast($this->single($value))),
            self::BETWEEN => $inner->whereBetween($column, $this->range($value)),
            self::CONTAINS_ANY => $this->applyJsonContains($inner, $column, $value, false),
            self::CONTAINS_ALL => $this->applyJsonContains($inner, $column, $value, true),
            default => throw new InvalidArgumentException("Custom field query operation [{$this->operation}] cannot filter values."),
        };
    }

    /**
     * The escape character has no builder method, so the placeholder and the escape
     * clause travel together as an expression and the pattern is bound right after the
     * clause that uses it, which keeps the bindings in the order of the compiled SQL.
     *
     * Case sensitivity follows the collation of the database, so the same pattern can
     * behave differently on MySQL and on PostgreSQL.
     *
     * @param  Builder<Model>  $inner
     */
    private function applyContains(Builder $inner, string $column, mixed $value): void
    {
        $pattern = new Expression("? escape '".self::LIKE_ESCAPE."'");

        $inner->where(function (Builder $group) use ($column, $pattern, $value): void {
            foreach ($this->many($value) as $item) {
                $group->orWhere($column, 'like', $pattern)
                    ->addBinding('%'.$this->escapeLike($this->text($item)).'%', 'where');
            }
        });
    }

    /** @param  Builder<Model>  $inner */
    private function applyJsonContains(Builder $inner, string $column, mixed $value, bool $all): void
    {
        $values = $this->many($value);

        if ($values === []) {
            return;
        }

        if ($all) {
            foreach ($values as $item) {
                $inner->whereJsonContains($column, $item);
            }

            return;
        }

        $inner->where(function (Builder $group) use ($column, $values): void {
            foreach ($values as $item) {
                $group->orWhereJsonContains($column, $item);
            }
        });
    }

    /**
     * A row is present when it exists and its typed column is not null, so a host
     * without any value row and a host with a null value are both absent.
     *
     * @param  Builder<Model>  $query
     */
    private function applyPresence(Builder $query, bool $present): void
    {
        $constraint = function (Builder $inner): void {
            $values = $inner->getModel();

            $inner->where($values->qualifyColumn('custom_field_id'), $this->field->getKey())
                ->whereNotNull($values->qualifyColumn($this->fieldType()->storageColumn()));
        };

        if ($present) {
            $query->whereHas('customFieldValues', $constraint);

            return;
        }

        $query->whereDoesntHave('customFieldValues', $constraint);
    }

    /**
     * Reads the request value of a presence filter as a switch, so a falsy value asks
     * for the opposite of the operation.
     */
    private function flag(mixed $value): bool
    {
        return filter_var($this->many($value)[0] ?? null, FILTER_VALIDATE_BOOLEAN);
    }

    private function escapeLike(string $value): string
    {
        return str_replace(
            [self::LIKE_ESCAPE, '%', '_'],
            [self::LIKE_ESCAPE.self::LIKE_ESCAPE, self::LIKE_ESCAPE.'%', self::LIKE_ESCAPE.'_'],
            $value,
        );
    }

    /** @return array<int, mixed> */
    private function many(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [$value];
    }

    private function single(mixed $value): mixed
    {
        if (is_array($value)) {
            throw InvalidFilterValue::make($this->describe($value));
        }

        return $value;
    }

    /** @return array<int, mixed> */
    private function range(mixed $value): array
    {
        $values = is_array($value) ? array_values($value) : explode(',', $this->text($value));

        if (count($values) !== 2) {
            throw InvalidFilterValue::make($this->describe($value));
        }

        return $this->castMany($values);
    }

    private function cast(mixed $value): mixed
    {
        return $value === null ? null : $this->fieldType()->serialize($value, $this->field);
    }

    /**
     * @param  array<int|string, mixed>  $values
     * @return array<int, mixed>
     */
    private function castMany(array $values): array
    {
        return array_values(array_map($this->cast(...), $values));
    }

    private function text(mixed $value): string
    {
        if (! is_scalar($value)) {
            throw InvalidFilterValue::make($this->describe($value));
        }

        return (string) $value;
    }

    private function describe(mixed $value): string
    {
        if (is_array($value)) {
            return implode(',', array_map($this->describe(...), $value));
        }

        return is_scalar($value) ? (string) $value : get_debug_type($value);
    }

    private function fieldType(): FieldType
    {
        /** @var CustomField $field */
        $field = $this->field;

        return $field->fieldType();
    }
}
