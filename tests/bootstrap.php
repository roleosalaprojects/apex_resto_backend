<?php

/**
 * PHPUnit Bootstrap File
 *
 * This file runs before any tests execute. It includes critical safety checks
 * to prevent tests from accidentally running against the production database.
 */

require __DIR__.'/../vendor/autoload.php';

// CRITICAL SAFETY CHECK: Verify we're using the testing database
// This runs BEFORE Laravel boots, checking the environment directly
$envDatabase = $_ENV['DB_DATABASE'] ?? $_SERVER['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: null;

if ($envDatabase !== 'testing') {
    fwrite(STDERR, "\n");
    fwrite(STDERR, "╔══════════════════════════════════════════════════════════════════╗\n");
    fwrite(STDERR, "║  CRITICAL ERROR: DATABASE SAFETY CHECK FAILED                    ║\n");
    fwrite(STDERR, "╠══════════════════════════════════════════════════════════════════╣\n");
    fwrite(STDERR, "║  Tests MUST run against the 'testing' database.                  ║\n");
    fwrite(STDERR, '║  Current database: '.str_pad($envDatabase ?: 'NOT SET', 44)."║\n");
    fwrite(STDERR, "║                                                                  ║\n");
    fwrite(STDERR, "║  This check prevents accidental data loss in production.         ║\n");
    fwrite(STDERR, "║                                                                  ║\n");
    fwrite(STDERR, "║  To fix:                                                         ║\n");
    fwrite(STDERR, "║  1. Ensure phpunit.xml has: force=\"true\" on DB_DATABASE          ║\n");
    fwrite(STDERR, "║  2. Ensure .env.testing has: DB_DATABASE=testing                 ║\n");
    fwrite(STDERR, "║  3. Run: vendor/bin/sail artisan config:clear                    ║\n");
    fwrite(STDERR, "╚══════════════════════════════════════════════════════════════════╝\n");
    fwrite(STDERR, "\n");
    exit(1);
}

// Additional check: Ensure we're not pointing at common production database names
$dangerousDatabaseNames = ['cookie_server', 'production', 'prod', 'live', 'main', 'apex', 'apex_pos'];
if (in_array($envDatabase, $dangerousDatabaseNames, true)) {
    fwrite(STDERR, "\n");
    fwrite(STDERR, "FATAL: Attempted to run tests against protected database: {$envDatabase}\n");
    fwrite(STDERR, "Tests are blocked to prevent data loss.\n\n");
    exit(1);
}
