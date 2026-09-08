<?php

declare(strict_types=1);

arch()->preset()->php();

arch()->preset()->security();

arch('it will not use dd(), ddd(), env(), or exit()')
    ->expect(['dd', 'ddd', 'env', 'exit'])
    ->each->not->toBeUsed();

arch('the package source declares strict types')
    ->expect('PlinCode\CustomFields')
    ->toUseStrictTypes();

arch('the package stays headless')
    ->expect('PlinCode\CustomFields')
    ->not->toUse([
        'Illuminate\Http\Request',
        'Illuminate\Routing\Controller',
        'Illuminate\Support\Facades\Route',
        'Illuminate\Support\Facades\View',
    ]);

arch('every shipped type implements the field type contract')
    ->expect('PlinCode\CustomFields\Types')
    ->toImplement('PlinCode\CustomFields\Contracts\FieldType');

arch('the package exceptions are throwable')
    ->expect('PlinCode\CustomFields\Exceptions')
    ->toExtend('Exception');
