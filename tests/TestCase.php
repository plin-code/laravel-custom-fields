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
     * The suite runs against an in memory database unless DB_DRIVER asks for
     * one of the servers, which is how the workflow exercises MySQL and
     * PostgreSQL. Pinning the connection also keeps a database/database.sqlite
     * left behind by composer build from making Testbench prefer the file,
     * whose published migrations would then collide with the ones loaded below.
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', $this->connectionConfiguration());
    }

    /** @return array<string, mixed> */
    protected function connectionConfiguration(): array
    {
        return match ($this->fromEnvironment('DB_DRIVER', 'sqlite')) {
            'mysql' => [
                'driver' => 'mysql',
                'host' => $this->fromEnvironment('DB_HOST', '127.0.0.1'),
                'port' => $this->fromEnvironment('DB_PORT', '3306'),
                'database' => $this->fromEnvironment('DB_DATABASE', 'custom_fields'),
                'username' => $this->fromEnvironment('DB_USERNAME', 'root'),
                'password' => $this->fromEnvironment('DB_PASSWORD', ''),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
            ],
            'pgsql' => [
                'driver' => 'pgsql',
                'host' => $this->fromEnvironment('DB_HOST', '127.0.0.1'),
                'port' => $this->fromEnvironment('DB_PORT', '5432'),
                'database' => $this->fromEnvironment('DB_DATABASE', 'custom_fields'),
                'username' => $this->fromEnvironment('DB_USERNAME', 'postgres'),
                'password' => $this->fromEnvironment('DB_PASSWORD', ''),
                'charset' => 'utf8',
                'prefix' => '',
                'search_path' => 'public',
            ],
            default => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        };
    }

    /** Reads a workflow variable without the env() helper the arch test forbids. */
    protected function fromEnvironment(string $key, string $default): string
    {
        $value = getenv($key);

        return $value === false || $value === '' ? $default : $value;
    }

    /**
     * The host tables belong to the fixtures rather than to the package.
     *
     * An in memory database starts empty for every test, while a MySQL or a
     * PostgreSQL server keeps what the previous test left, so they are dropped
     * on both ends of the test rather than only created.
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->dropLeftoverTables();
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->dropHostTables();

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

        $this->beforeApplicationDestroyed(fn () => $this->dropHostTables());
    }

    protected function dropHostTables(): void
    {
        foreach (['articles', 'projects', 'authors'] as $table) {
            Schema::dropIfExists($table);
        }
    }

    /**
     * Clears whatever a previous run left on a server.
     *
     * An in memory database has nothing to clear. A MySQL or a PostgreSQL
     * server keeps the tables of a run that was interrupted, and the migrator
     * would then try to create them again, so every later test fails on a
     * schema it never made.
     */
    protected function dropLeftoverTables(): void
    {
        if ($this->fromEnvironment('DB_DRIVER', 'sqlite') === 'sqlite') {
            return;
        }

        foreach ([
            'custom_field_values',
            'custom_fields',
            'uuid_documents',
            'ulid_documents',
            'migrations',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        $this->dropHostTables();
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
