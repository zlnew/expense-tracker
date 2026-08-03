<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * INCIDENT 2026-08-03: the test suite once ran against the REAL pgsql
     * expense_tracker database and RefreshDatabase's migrate:fresh wiped it.
     *
     * Why it happened: phpunit.xml env vars are only applied via putenv/$_ENV,
     * but Laravel's env() reads $_SERVER FIRST — and $_SERVER is populated
     * from the process environment at PHP start. When tests ran inside the
     * deployed container (which carries real DB_* pgsql vars from compose),
     * the pgsql config won and the suite nuked production data.
     *
     * This guard forces sqlite :memory: in ALL three env sources before the
     * app boots, then hard-fails if the app still isn't on sqlite. A test
     * run can never touch a real database again.
     */
    public function createApplication()
    {
        $force = [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'DB_URL' => '',
        ];

        foreach ($force as $key => $value) {
            $_SERVER[$key] = $value;
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }

        $app = parent::createApplication();

        $default = $app['config']->get('database.default');
        $database = $app['config']->get("database.connections.{$default}.database");

        if ($default !== 'sqlite' || $database !== ':memory:') {
            throw new \RuntimeException(
                "Test database safety guard FAILED: default={$default} database={$database}. ".
                'Tests MUST run on sqlite :memory:. Refusing to run — a real database is in scope.'
            );
        }

        return $app;
    }
}
