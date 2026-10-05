<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Refresh the application instance, refusing to keep a test
     * environment whose database is not isolated.
     *
     * This override runs BEFORE setUpTraits() (the lifecycle step where
     * RefreshDatabase invokes migrate:fresh), so a misconfigured database
     * aborts the test run before any migration or seeding work can touch
     * a real MySQL database such as maternity_system1.
     */
    protected function refreshApplication()
    {
        $this->app = $this->createApplication();

        $this->ensureDatabaseIsolation();

        $this->registerMysqlCompatibilityFunctions();
    }

    /**
     * Test-only shim: register the MySQL-only SQL functions used by raw
     * queries in production code (YEAR/MONTH/DAY in analytics and
     * dashboard queries, CONCAT in risk search) on the isolated SQLite
     * connection, so those queries behave like they do on MySQL.
     *
     * This intentionally lives in test infrastructure only: no production
     * query, model, or service is changed, and the MySQL code path used
     * at runtime is untouched. Returns NULL for NULL input, matching
     * MySQL semantics.
     */
    private function registerMysqlCompatibilityFunctions(): void
    {
        $pdo = $this->app->make('db')->connection('sqlite')->getPdo();

        $extract = static fn (?string $format): callable => static function ($value) use ($format) {
            if ($value === null || $value === '') {
                return null;
            }

            $timestamp = strtotime($value);

            return $timestamp === false ? null : (int) date($format, $timestamp);
        };

        $pdo->sqliteCreateFunction('year', $extract('Y'));
        $pdo->sqliteCreateFunction('month', $extract('n'));
        $pdo->sqliteCreateFunction('day', $extract('j'));

        $pdo->sqliteCreateFunction(
            'concat',
            static fn (...$parts): ?string => in_array(null, $parts, true)
                ? null
                : implode('', $parts)
        );
    }

    /**
     * Hard safety guard: tests may only run against an in-memory SQLite
     * database, and only when no cached configuration exists.
     *
     * A cached config (bootstrap/cache/config.php) bypasses phpunit.xml
     * entirely, which previously routed a test run to MySQL and wiped
     * maternity_system1. Refuse to run instead of risking that path.
     */
    private function ensureDatabaseIsolation(): void
    {
        $config = $this->app->make('config');

        $databaseDefault = $config->get('database.default');
        $sqliteDatabase = $config->get('database.connections.sqlite.database');
        $configCacheExists = file_exists(
            $this->app->bootstrapPath('cache/config.php')
        );

        if ($databaseDefault !== 'sqlite'
            || $sqliteDatabase !== ':memory:'
            || $configCacheExists) {
            throw new RuntimeException(
                'Test database isolation guard: tests are refusing to run '
                . 'because the database is not isolated. '
                . "Expected database.default='sqlite' and "
                . "database.connections.sqlite.database=':memory:', with no "
                . 'bootstrap/cache/config.php present. '
                . sprintf(
                    "(actual: database.default='%s', sqlite database='%s', "
                    . 'config cache exists=%s). ',
                    var_export($databaseDefault, true),
                    var_export($sqliteDatabase, true),
                    $configCacheExists ? 'yes' : 'no'
                )
                . 'Run "php artisan optimize:clear" and verify phpunit.xml '
                . 'before running tests. Tests must NEVER use MySQL or the '
                . 'maternity_system1 database.'
            );
        }
    }
}
