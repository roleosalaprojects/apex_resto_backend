<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Passport\Passport;

abstract class TestCase extends BaseTestCase
{
    /**
     * Database names that are protected from test execution.
     * Tests will fail immediately if connected to any of these databases.
     */
    protected array $protectedDatabases = [
        'cookie_server',
        'production',
        'prod',
        'live',
        'main',
        'apex',
        'apex_pos',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertDatabaseIsSafeForTesting();

        // Load Passport keys for testing
        Passport::loadKeysFrom(storage_path());
    }

    /**
     * Verify the test is running against a safe database.
     * This prevents accidental data loss from RefreshDatabase trait.
     */
    protected function assertDatabaseIsSafeForTesting(): void
    {
        $database = config('database.connections.mysql.database');

        // Must be exactly 'testing'
        if ($database !== 'testing') {
            $this->fail(
                "DATABASE SAFETY CHECK FAILED\n".
                "═══════════════════════════════════════════════════════════\n".
                "Tests must run against 'testing' database.\n".
                "Currently connected to: '{$database}'\n".
                "═══════════════════════════════════════════════════════════\n".
                "This check prevents RefreshDatabase from wiping production data.\n\n".
                "To fix:\n".
                "1. Run: vendor/bin/sail artisan config:clear\n".
                "2. Verify phpunit.xml has force=\"true\" on DB_DATABASE\n".
                '3. Verify .env.testing has DB_DATABASE=testing'
            );
        }

        // Extra safety: block known production database names
        if (in_array($database, $this->protectedDatabases, true)) {
            $this->fail(
                "CRITICAL: Attempted to run tests against protected database: {$database}\n".
                "Add this database to the 'testing' configuration or update protectedDatabases."
            );
        }
    }
}
