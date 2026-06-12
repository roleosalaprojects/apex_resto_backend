---
name: api-endpoint
description: Scaffold a new POS API endpoint with controller, form request, route, and feature test following project conventions.
argument-hint: [resource-name] [action]
allowed-tools: Bash(vendor/bin/sail artisan make:*), Bash(vendor/bin/sail bin pint:*), Read, Grep, Glob, Edit, Write
---

# Scaffold API Endpoint

Create a new POS API endpoint following this project's conventions.

## Arguments

- `$0` — Resource name (e.g. `ShiftReading`, `Voucher`)
- `$1` — Action (optional, e.g. `index`, `store`, `save`). Defaults to full CRUD.

## Steps

1. **Check existing files** — Look for existing controller, model, form request, and test files before creating anything new.

2. **Controller** — Create in `app/Http/Controllers/API/v1/pos/` following sibling controller patterns:
   - Use constructor injection where needed
   - Use `auth()->user()->user_id` for data scoping
   - Return JSON responses matching existing patterns

3. **Form Request** — Create in `app/Http/Requests/{ResourceName}/` following existing request patterns:
   - Check sibling form requests (e.g. `app/Http/Requests/ZReading/StoreRequest.php`) for rule style
   - Use array-based validation rules (project convention)

4. **Route** — Add to `routes/api/pos.php` inside the `auth:api` middleware group:
   - Use kebab-case prefix (e.g. `shift-readings`)
   - Follow the grouping pattern used by sibling routes

5. **Feature Test** — Create in `tests/Feature/API/v1/pos/` using PHPUnit:
   - Use model factories for test data setup
   - Test happy paths, validation failures, and authorization
   - Use `test_snake_case` method naming (enforced by Pint)

6. **Run Pint** — `vendor/bin/sail bin pint --dirty`

7. **Run the new tests** to verify everything works.

## Conventions

- API routes use prefix `v1`, middleware `auth:api`
- Use `auth()->user()->user_id` (not `auth()->id()`)
- Role checks: `->sls` for sales, `->invntry` for inventory, `->cstmr` for customers
- Models use `user_id` scoping
