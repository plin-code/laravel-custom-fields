<?php

declare(strict_types=1);

namespace PlinCode\CustomFields\Tests;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use PlinCode\CustomFields\CustomFieldsServiceProvider;
use PlinCode\EloquentSorts\EloquentSortsServiceProvider;
use Spatie\QueryBuilder\QueryBuilderServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            QueryBuilderServiceProvider::class,
            EloquentSortsServiceProvider::class,
            CustomFieldsServiceProvider::class,
        ];
    }

    /**
     * The suite always runs against an in memory database.
     *
     * Without this, a database/database.sqlite left behind by composer build
     * makes Testbench prefer the file, and the package migrations it published
     * into the skeleton then collide with the ones loaded below.
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Schema::create('authors', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        Schema::create('articles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('author_id')->nullable();
            $table->string('title');
            $table->timestamps();
        });

        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->timestamps();
        });
    }

    /**
     * Recreates the package tables under the key configuration the test just set.
     *
     * The key types have to be chosen before the migrations run, so a test that
     * exercises uuid or ulid keys rebuilds the two tables through the very
     * migrations the consumer publishes.
     */
    protected function rebuildPackageTables(): void
    {
        $migrations = [];

        foreach ((array) glob(__DIR__.'/../database/migrations/*.php') as $file) {
            $migration = require $file;

            if ($migration instanceof Migration) {
                $migrations[] = $migration;
            }
        }

        foreach (array_reverse($migrations) as $migration) {
            $migration->down();
        }

        foreach ($migrations as $migration) {
            $migration->up();
        }
    }
}
