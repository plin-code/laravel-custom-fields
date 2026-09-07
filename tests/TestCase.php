<?php

declare(strict_types=1);

namespace PlinCode\CustomFields\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use PlinCode\CustomFields\CustomFieldsServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            CustomFieldsServiceProvider::class,
        ];
    }
}
