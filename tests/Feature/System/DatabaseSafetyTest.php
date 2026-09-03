<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * INCIDENT 2026-08-03: the test suite once ran against the REAL pgsql
 * expense_tracker database (env vars leaked from the deployed container,
 * phpunit.xml wasn't forced, RefreshDatabase ran migrate:fresh, data lost).
 *
 * This test is a tripwire: if the environment ever stops forcing sqlite,
 * the database would be a real one and RefreshDatabase would wipe it.
 * The TestCase::createApplication() guard hard-fails earlier (before app
 * boot) — this test documents the invariant and would also go red.
 */
test('test suite must run on sqlite :memory: (data-loss safety tripwire)', function () {
    expect(config('database.default'))->toBe('sqlite');
    expect(config('database.connections.sqlite.database'))->toBe(':memory:');
});
