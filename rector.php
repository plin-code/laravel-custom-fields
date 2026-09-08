<?php

declare(strict_types=1);

use Rector\CodingStyle\Rector\FuncCall\FunctionFirstClassCallableRector;
use Rector\Config\RectorConfig;
use Rector\Php55\Rector\String_\StringClassNameToClassConstantRector;
use Rector\Php84\Rector\MethodCall\NewMethodCallWithoutParenthesesRector;
use RectorLaravel\Rector\ClassMethod\MakeModelAttributesAndScopesProtectedRector;
use RectorLaravel\Rector\FuncCall\AppToResolveRector;
use RectorLaravel\Set\LaravelSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/config',
        __DIR__.'/database',
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->withPhpSets()
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
    )
    ->withSets([
        LaravelSetList::LARAVEL_CODE_QUALITY,
    ])
    ->withSkip([
        // The package resolves out of the container with app(), which is the
        // convention the rest of the Laravel ecosystem reads.
        AppToResolveRector::class,

        // HasCustomFields is a trait consumers apply to their own models, and
        // whereCustomField is documented public API. The rule assumes a model.
        MakeModelAttributesAndScopesProtectedRector::class => [
            __DIR__.'/src/Concerns/HasCustomFields.php',
        ],

        // Architecture tests describe namespaces and classes as strings on
        // purpose, so the string form is the point rather than an oversight.
        StringClassNameToClassConstantRector::class => [
            __DIR__.'/tests/ArchTest.php',
        ],

        // Pest test bodies keep the explicit call forms the suite was written
        // with, so a style pass never rewrites an assertion out from under it.
        FunctionFirstClassCallableRector::class => [
            __DIR__.'/tests',
        ],

        // This one rewrites at print time rather than through the rule, so
        // Rector attributes no rule to the change and a path scoped skip never
        // matches it. Skipping it outright is the only form that holds.
        NewMethodCallWithoutParenthesesRector::class,
    ]);
