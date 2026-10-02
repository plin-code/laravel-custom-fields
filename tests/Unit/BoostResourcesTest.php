<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\ServiceProvider;
use PlinCode\CustomFields\Concerns\HasCustomFields;
use PlinCode\CustomFields\Contracts\FieldType;
use PlinCode\CustomFields\Facades\CustomFields;
use PlinCode\CustomFields\Query\CustomFieldFilter;
use PlinCode\CustomFields\Query\CustomFieldSorter;
use Symfony\Component\Yaml\Yaml;

const BOOST_GUIDELINE = __DIR__.'/../../resources/boost/guidelines/core.blade.php';

const BOOST_SKILL = __DIR__.'/../../resources/boost/skills/custom-fields-development/SKILL.md';

function boostGuideline(): string
{
    return Blade::render((string) file_get_contents(BOOST_GUIDELINE));
}

function boostSkill(): string
{
    return (string) file_get_contents(BOOST_SKILL);
}

/** @return array<string, string> */
function boostDocuments(): array
{
    return ['guideline' => boostGuideline(), 'skill' => boostSkill()];
}

/**
 * Every package or framework class the documents name by its fully qualified name,
 * plus the ones their code examples import, keyed by short name.
 *
 * @return array<string, string>
 */
function boostKnownClasses(string $text): array
{
    preg_match_all('/(?<![\w\\\\])((?:PlinCode|Spatie|Illuminate)(?:\\\\[A-Z]\w*)+)/', $text, $matches);

    $classes = [];

    foreach (array_unique($matches[1]) as $class) {
        $classes[class_basename($class)] = $class;
    }

    return $classes;
}

/**
 * Classes the examples declare or import from the consumer application, such as
 * a Patient model or a CountryType, mapped to the package class they build on.
 *
 * @return array<string, string|null>
 */
function boostExampleClasses(string $text, array $known): array
{
    $examples = [];

    preg_match_all('/use App\\\\[\w\\\\]*\\\\(\w+);/', $text, $imports);

    foreach ($imports[1] as $class) {
        $examples[$class] = Model::class;
    }

    preg_match_all('/class (\w+) extends (\w+)/', $text, $declarations, PREG_SET_ORDER);

    foreach ($declarations as [, $class, $parent]) {
        $examples[$class] = $known[$parent] ?? null;
    }

    return $examples;
}

/**
 * Where a method named in the documents may live when the receiver is not spelled out.
 *
 * @param  array<string, string>  $known
 * @return array<int, string>
 */
function boostMethodHosts(array $known): array
{
    return array_values(array_unique([
        ...array_values($known),
        PlinCode\CustomFields\CustomFields::class,
        HasCustomFields::class,
        Model::class,
        Builder::class,
        QueryBuilder::class,
        Collection::class,
        EloquentCollection::class,
        ServiceProvider::class,
    ]));
}

function boostMethodExists(string $class, string $method): bool
{
    if (method_exists($class, $method)) {
        return true;
    }

    if (is_subclass_of($class, Facade::class)) {
        return method_exists($class::getFacadeRoot(), $method);
    }

    // Eloquent forwards static and instance calls it does not define to the builder,
    // and a scope is reached without its prefix.
    if (is_a($class, Model::class, true) || $class === HasCustomFields::class) {
        return method_exists(Builder::class, $method)
            || method_exists(QueryBuilder::class, $method)
            || method_exists($class, 'scope'.ucfirst($method))
            || method_exists(HasCustomFields::class, 'scope'.ucfirst($method));
    }

    return false;
}

/** Renders a method signature the way the documents write it, with short type names. */
function boostSignature(ReflectionMethod $method, bool $withReturn = true): string
{
    $type = static fn (?ReflectionType $type): string => $type instanceof ReflectionType ? preg_replace_callback(
        '/[\w\\\\]+/',
        static fn (array $match): string => class_basename($match[0]),
        (string) $type,
    ) : '';

    $parameters = array_map(static function (ReflectionParameter $parameter) use ($type): string {
        $signature = trim($type($parameter->getType()).' $'.$parameter->getName());

        if ($parameter->isDefaultValueAvailable()) {
            $signature .= ' = '.strtolower(var_export($parameter->getDefaultValue(), true));
        }

        return $signature;
    }, $method->getParameters());

    $signature = $method->getName().'('.implode(', ', $parameters).')';

    return $withReturn ? $signature.': '.$type($method->getReturnType()) : $signature;
}

/** @return array<int, string> */
function boostStorageColumns(): array
{
    /** @var array<int, string> $columns */
    $columns = (new ReflectionClass(HasCustomFields::class))->getConstant('STORAGE_COLUMNS');

    return $columns;
}

/** @return array<int, string> */
function boostQueryOperations(): array
{
    return [...CustomFieldFilter::operations(), CustomFieldSorter::SORT];
}

/**
 * Type keys the examples declare themselves, such as a CountryType returning 'country'.
 *
 * @return array<int, string>
 */
function boostDeclaredTypeKeys(string $text): array
{
    preg_match_all("/function key\(\): string\s*\{\s*return '(\w+)';/", $text, $matches);

    return $matches[1];
}

describe('Boost guidelines', function (): void {
    it('ships a single core guidelines file', function (): void {
        expect(file_exists(BOOST_GUIDELINE))->toBeTrue();
    });

    it('renders as blade without leaving directives behind', function (): void {
        expect(boostGuideline())->not->toContain('@verbatim')
            ->not->toContain('@endverbatim')
            ->not->toContain('{{');
    });

    it('names the trait, the registration and the main api', function (): void {
        expect(boostGuideline())->toContain('HasCustomFields')
            ->toContain('registerEntity')
            ->toContain('setCustomFields')
            ->toContain('getCustomField')
            ->toContain('queryOptionsFor')
            ->toContain('whereCustomField');
    });

    it('wraps its code examples in code-snippet tags', function (): void {
        expect(boostGuideline())->toContain('<code-snippet')
            ->toContain('</code-snippet>');
    });

    it('keeps the guidelines short enough to stay in context', function (): void {
        expect(strlen(boostGuideline()))->toBeLessThan(6000);
    });

    it('suggests laravel boost without requiring it', function (): void {
        $composer = json_decode((string) file_get_contents(__DIR__.'/../../composer.json'), true);

        expect($composer['suggest']['laravel/boost'] ?? null)->toBeString()
            ->not->toBe('')
            ->and($composer['require']['laravel/boost'] ?? null)->toBeNull()
            ->and($composer['require-dev']['laravel/boost'] ?? null)->toBeNull();
    });
});

describe('Boost skill', function (): void {
    it('ships a skill file in a folder named after the skill', function (): void {
        expect(file_exists(BOOST_SKILL))->toBeTrue();
    });

    it('declares the frontmatter boost requires', function (): void {
        $content = boostSkill();

        expect($content)->toStartWith("---\n");

        preg_match('/^---\s*\n(.*?)\n---\s*\n/s', $content, $matches);

        $frontmatter = Yaml::parse($matches[1] ?? '');

        expect($frontmatter)->toBeArray()
            ->and($frontmatter['name'] ?? null)->toBe('custom-fields-development')
            ->and($frontmatter['description'] ?? null)->toBeString()
            ->and($frontmatter['description'] ?? '')->not->toBe('');
    });

    it('tells the agent when to use the skill and how to add a type', function (): void {
        expect(boostSkill())->toContain('## When to use this skill')
            ->toContain('registerType')
            ->toContain('FieldType');
    });

    it('does not repeat the guidelines', function (): void {
        $skill = boostSkill();

        expect($skill)->not->toBe(boostGuideline());

        preg_match_all('/<code-snippet[^>]*>(.*?)<\/code-snippet>/s', boostGuideline(), $snippets);

        foreach ($snippets[1] as $snippet) {
            expect($skill)->not->toContain(trim($snippet));
        }
    });
});

/*
 * The documents are read by agents that cannot tell a stale name from a real one, so
 * every identifier they mention is extracted from the text and checked against the
 * code. A rename, a removed method, a dropped config key or a changed signature fails
 * here instead of misleading the next agent.
 */
describe('Boost drift', function (): void {
    it('names only classes, interfaces and traits that exist', function (string $document): void {
        foreach (boostKnownClasses(boostDocuments()[$document]) as $class) {
            expect(class_exists($class) || interface_exists($class) || trait_exists($class))
                ->toBeTrue("{$document} names [{$class}], which does not exist.");
        }
    })->with(['guideline', 'skill']);

    it('calls only static methods and constants that exist', function (string $document): void {
        $text = boostDocuments()[$document];
        $known = boostKnownClasses(implode("\n", boostDocuments()));
        $examples = boostExampleClasses($text, $known);

        preg_match_all('/\b([A-Z]\w*)::(\w+)(\()?/', $text, $calls, PREG_SET_ORDER);

        expect($calls)->not->toBeEmpty();

        foreach ($calls as $call) {
            [, $short, $member] = $call;
            $isCall = isset($call[3]);

            if ($member === 'class') {
                continue;
            }

            if (array_key_exists($short, $examples) && ! isset($known[$short])) {
                $parent = $examples[$short];

                expect($parent !== null && boostMethodExists($parent, $member))
                    ->toBeTrue("{$document} calls [{$short}::{$member}()], which [{$parent}] does not provide.");

                continue;
            }

            expect(isset($known[$short]))->toBeTrue("{$document} uses [{$short}::{$member}] without naming the class in full anywhere.");

            $class = $known[$short];

            if (! $isCall) {
                expect(defined($class.'::'.$member))->toBeTrue("{$document} names the constant [{$class}::{$member}], which does not exist.");

                continue;
            }

            expect(boostMethodExists($class, $member))->toBeTrue("{$document} calls [{$class}::{$member}()], which does not exist.");
        }
    })->with(['guideline', 'skill']);

    it('calls only instance methods that exist', function (string $document): void {
        $text = boostDocuments()[$document];
        $hosts = boostMethodHosts(boostKnownClasses(implode("\n", boostDocuments())));

        preg_match_all('/->(\w+)\(/', $text, $arrows);
        preg_match_all('/`(?:\$\w+->)?([a-z]\w*)\(/', $text, $inline);

        $methods = array_unique([...$arrows[1], ...$inline[1]]);

        expect($methods)->not->toBeEmpty();

        foreach ($methods as $method) {
            $found = array_filter($hosts, static fn (string $host): bool => boostMethodExists($host, $method));

            expect($found)->not->toBeEmpty("{$document} calls [{$method}()], which no referenced class provides.");
        }
    })->with(['guideline', 'skill']);

    it('writes the trait signatures exactly as the code declares them', function (): void {
        preg_match_all('/^- `(\w+\(.*?\): \w+)`$/m', boostSkill(), $matches);

        expect($matches[1])->not->toBeEmpty();

        foreach ($matches[1] as $documented) {
            $name = strstr($documented, '(', true);

            expect(boostSignature(new ReflectionMethod(HasCustomFields::class, (string) $name)))->toBe($documented);
        }

        preg_match('/`registerEntity\((.*?)\)`/', boostSkill(), $entity);

        expect(boostSignature(new ReflectionMethod(PlinCode\CustomFields\CustomFields::class, 'registerEntity'), false))
            ->toBe('registerEntity('.($entity[1] ?? '').')');
    });

    it('lists the whole field type contract and nothing else', function (): void {
        preg_match_all('/^public (?:static )?function (\w+\(.*?\): \w+);$/m', boostSkill(), $matches);

        $contract = array_map(
            static fn (ReflectionMethod $method): string => boostSignature($method),
            (new ReflectionClass(FieldType::class))->getMethods(),
        );

        expect($matches[1])->toEqualCanonicalizing($contract);
    });

    it('names only config keys the package config defines, and all of them', function (string $document): void {
        $text = boostDocuments()[$document];
        $config = (array) config('laravel-custom-fields');

        preg_match_all('/(?<![\w\/-])laravel-custom-fields\.([a-z_]+(?:\.[a-z_]+)*)/', $text, $prefixed);
        preg_match_all('/`([a-z_]+(?:\.[a-z_]+)+)`/', $text, $dotted);

        $keys = $prefixed[1];

        foreach ($dotted[1] as $key) {
            if (array_key_exists(explode('.', $key)[0], $config)) {
                $keys[] = $key;
            }
        }

        foreach (array_unique($keys) as $key) {
            expect(Arr::has($config, $key))->toBeTrue("{$document} names the config key [{$key}], which does not exist.");
        }

        if ($document === 'guideline') {
            foreach (array_keys(Arr::dot($config)) as $key) {
                expect($text)->toContain('laravel-custom-fields.'.$key);
            }
        }
    })->with(['guideline', 'skill']);

    it('names only registered field types, and all of them', function (string $document): void {
        $text = boostDocuments()[$document];
        $registered = array_keys(CustomFields::types());
        $declared = boostDeclaredTypeKeys($text);

        preg_match_all("/'type' => '(\w+)'/", $text, $used);

        foreach ($used[1] as $key) {
            expect([...$registered, ...$declared])->toContain($key);
        }

        foreach ($registered as $key) {
            expect($text)->toContain("`{$key}`");
        }
    })->with(['guideline', 'skill']);

    it('describes the storage column and the operations of every type as the code does', function (): void {
        preg_match_all('/^\| (`\w+`(?:, `\w+`)*) \| `(\w+)` \| [^|]+ \| ([^|]+) \|$/m', boostSkill(), $rows, PREG_SET_ORDER);

        $documented = [];

        foreach ($rows as [, $keys, $column, $operations]) {
            preg_match_all('/`(\w+)`/', $keys, $typeKeys);
            preg_match('/^same as `(\w+)`$/', trim($operations), $same);
            preg_match_all('/`(\w+)`/', $operations, $listed);

            foreach ($typeKeys[1] as $key) {
                $documented[$key] = [
                    'column' => $column,
                    'operations' => $same === [] ? $listed[1] : $documented[$same[1]]['operations'],
                ];
            }
        }

        $actual = array_map(static fn (FieldType $type): array => [
            'column' => $type->storageColumn(),
            'operations' => $type->queryOperations(),
        ], CustomFields::types());

        expect(array_keys($documented))->toEqualCanonicalizing(array_keys($actual));

        foreach ($actual as $key => $type) {
            expect($documented[$key]['column'])->toBe($type['column'], "storage column of [{$key}]")
                ->and($documented[$key]['operations'])->toEqualCanonicalizing($type['operations']);
        }
    });

    it('names only storage columns the values table has, and the guideline names all of them', function (string $document): void {
        $text = boostDocuments()[$document];

        preg_match_all('/\bvalue_[a-z]+\b/', $text, $columns);

        foreach (array_unique($columns[0]) as $column) {
            expect(boostStorageColumns())->toContain($column);
        }

        if ($document === 'guideline') {
            foreach (boostStorageColumns() as $column) {
                expect($text)->toContain("`{$column}`");
            }
        }
    })->with(['guideline', 'skill']);

    it('names only query operations the package serves', function (string $document): void {
        $text = boostDocuments()[$document];
        $prefix = preg_quote(CustomFields::keyPrefix(), '/');

        preg_match_all('/filter\['.$prefix.'[\w-]+:(\w+)\]/', $text, $filters);
        preg_match_all("/function queryOperations\(\): array\s*\{\s*return \[(.*?)\];/s", $text, $declared);

        $operations = $filters[1];

        foreach ($declared[1] as $list) {
            preg_match_all("/'(\w+)'/", $list, $names);
            $operations = [...$operations, ...$names[1]];
        }

        expect($operations)->not->toBeEmpty();

        foreach (array_unique($operations) as $operation) {
            expect(boostQueryOperations())->toContain($operation);
        }

        expect($text)->toContain($prefix === '' ? 'filter[' : 'filter['.CustomFields::keyPrefix());
    })->with(['guideline', 'skill']);

    it('lists the whole operation vocabulary in the skill', function (): void {
        foreach (boostQueryOperations() as $operation) {
            expect(boostSkill())->toContain("`{$operation}`");
        }
    });

    it('runs only artisan commands that are registered', function (): void {
        preg_match_all('/php artisan ([\w:-]+)/', implode("\n", boostDocuments()), $commands);

        expect($commands[1])->not->toBeEmpty();

        foreach (array_unique($commands[1]) as $command) {
            expect(Artisan::all())->toHaveKey($command);
        }
    });

    it('names only publish tags the service provider registers', function (): void {
        $text = implode("\n", boostDocuments());

        preg_match_all('/--tag="([\w-]+)"/', $text, $flags);
        preg_match_all('/`(laravel-custom-fields(?:-[a-z]+)?)`/', $text, $inline);

        $tags = array_unique([...$flags[1], ...$inline[1]]);

        expect($tags)->not->toBeEmpty();

        foreach ($tags as $tag) {
            expect(ServiceProvider::publishableGroups())->toContain($tag);
        }
    });

    it('names only migrations the package ships', function (): void {
        preg_match_all('/`(create_\w+_table)`/', implode("\n", boostDocuments()), $migrations);

        expect($migrations[1])->not->toBeEmpty();

        foreach ($migrations[1] as $migration) {
            expect(glob(__DIR__.'/../../database/migrations/*_'.$migration.'.php'))->not->toBeEmpty();
        }
    });
});
