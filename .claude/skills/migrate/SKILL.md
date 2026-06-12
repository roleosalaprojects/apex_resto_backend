---
name: migrate
description: Run or manage database migrations through Sail. Supports migrate, rollback, fresh, and status.
argument-hint: [action]
allowed-tools: Bash(vendor/bin/sail artisan migrate:*)
---

# Database Migrations

Run database migrations through Laravel Sail.

## Arguments

- `$ARGUMENTS` — Action to perform. Defaults to `migrate` if omitted.

## Actions

| Argument | Command |
|----------|---------|
| *(empty)* | `vendor/bin/sail artisan migrate` |
| `rollback` | `vendor/bin/sail artisan migrate:rollback` |
| `fresh` | `vendor/bin/sail artisan migrate:fresh --seed` |
| `status` | `vendor/bin/sail artisan migrate:status` |
| `refresh` | `vendor/bin/sail artisan migrate:refresh --seed` |

## Rules

- Always use `vendor/bin/sail artisan` (never `php artisan` directly)
- Pass `--no-interaction` to all commands
- For `fresh` and `refresh`, warn the user this will DROP all tables before proceeding
- Report the result: how many migrations ran, any errors
