---
name: test
description: Run tests for a specific file, filter, or the full suite. Use when the user says "run tests", "test this", or after code changes.
argument-hint: [file-or-filter]
allowed-tools: Bash(vendor/bin/sail artisan test:*), Read, Grep, Glob
---

# Run Tests

Run PHPUnit tests through Laravel Sail.

## Behavior

1. If `$ARGUMENTS` is provided:
   - If it looks like a file path (contains `/` or `.php`), run: `vendor/bin/sail artisan test $ARGUMENTS`
   - If it looks like a test method name, run: `vendor/bin/sail artisan test --filter=$ARGUMENTS`
   - If it's a class name (e.g. `ShiftReadingController`), find the matching test file under `tests/` and run it
2. If no arguments are provided:
   - Look at recently modified files (git diff --name-only) to identify related test files
   - Run those specific tests first
   - If no modified files found, ask the user what to test

## Rules

- Always use `vendor/bin/sail artisan test` (never phpunit directly)
- Run the **minimum** number of tests needed — use `--filter` or specific file paths
- After running, summarize: total tests, passed, failed, and any error details
- If tests fail, read the relevant source code and suggest fixes
- Do NOT modify test files unless explicitly asked
