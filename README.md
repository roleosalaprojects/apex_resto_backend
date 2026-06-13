# Apex Resto Backend

Laravel 12 backend for the Apex restaurant POS platform — composite items (recipes), waiter ordering → kitchen display → cashier settlement, reservations, dine-in/take-out/delivery, and BIR RMO 24-2023 (Annex F) compliance. Forked from apex_backend with a fresh history.

- **PHP**: 8.4
- **Database**: MySQL 8
- **Frontend**: Tailwind v4 + Livewire v3 + Vite
- **Local dev**: Laravel Sail (Docker)

---

## First-time setup

Public registration is intentionally disabled on every administrative surface (`/admin`, `/superadmin`, the POS API, the image-updater API). Bootstrapping a fresh install is a **two-step** flow:

1. Create the first **superadmin** via the CLI.
2. Sign in as that superadmin and create the **tenant-owner admin** through the `/superadmin` UI (which also bootstraps the default roles, store, tax rate, category, walk-in customer, and POS settings for the tenant).

You only need to do this **once per fresh database**.

### Step 1 — Create the first superadmin (CLI)

The `/superadmin` panel is the system-level control surface (BIR readings, receipts, tenant adjustments). It uses its own `admins` table, separate from the per-tenant `users` table.

```bash
vendor/bin/sail artisan apex:create-superadmin
```

You will be prompted for name, email, and password (minimum 8 characters). For automated / scripted setup, pass everything as options:

```bash
vendor/bin/sail artisan apex:create-superadmin \
    --name="Owner" \
    --email=owner@example.com \
    --password=changemenow
```

If an admin with that email already exists, the command refuses unless you pass `--force`, which resets the password without changing anything else:

```bash
vendor/bin/sail artisan apex:create-superadmin \
    --email=owner@example.com \
    --password=newpassword \
    --force
```

### Step 2 — Sign in and create the tenant-owner admin (UI)

Open `/superadmin/login`, sign in with the superadmin credentials from step 1, then go to **Manage Admins → Add Admin**.

Each superadmin is intended to create **exactly one** tenant-owner admin — the 1:1 superadmin↔tenant relationship is what keeps multi-tenant data isolated. (Hard enforcement of the 1:1 constraint is a planned follow-up; do not create multiple tenant-owner admins from one superadmin account in the meantime.)

The `/superadmin/admin/create` form bootstraps the entire tenant in one transaction:

- the tenant-owner **User** (with `user_id` self-referencing their own `id`)
- the **Owner**, **BIR**, and **Bagger** roles
- a default **BIR Officer** employee account
- the owner's **Employee** record
- a default **Tax** rate (12% VAT)
- a default **Category** (`NO CATEGORY`)
- a default **Store**
- a default walk-in **Customer**
- a default **Supplier** (`NO SUPPLIER`)
- the tenant's **PosSetting** row

After this, additional staff (cashiers, managers, etc.) are created via **Admin → Employees** in the web UI.

### Why not a CLI for the admin step?

There is an `apex:create-admin` artisan command in the codebase, but it is **not** the recommended path for first-time setup. It creates only the User + Admin role, skipping the store / tax / category / customer / supplier / POS-settings bootstrap that the `/superadmin` UI does. Use it only as an escape hatch (e.g., the superadmin UI is unreachable and you need to seed an admin account from SSH).

```bash
# Escape hatch only — prefer the /superadmin UI flow above.
vendor/bin/sail artisan apex:create-admin \
    --name="Owner" \
    --email=owner@example.com \
    --password=changemenow
```

---

## Running tests

```bash
vendor/bin/sail artisan test
```

To run a focused subset:

```bash
vendor/bin/sail artisan test tests/Feature/Console/ApexCreateAdminTest.php
vendor/bin/sail artisan test --filter=test_creates_admin_role_when_missing
```

---

## Code style

```bash
vendor/bin/sail bin pint --dirty
```

Pint runs the project's style profile and rewrites in place. CI expects a clean run.
