# apex_backend — `credit_payment` Higher-Access Permission Type

**Scope:** Add `credit_payment` as a valid `permission_type` for the higher-access flow so cashiers without `crdt_pymnt` can request manager approval to enter the Credit Payment screen. POS (`apex_pos`) and dashboard (`apex_dashboard`) sides are already (or will be) wired up — this picks up the backend (`apex_backend`).

**Status:** POS already submits `permission_type: credit_payment` and routes the approval callback. Backend currently rejects with `"The selected permission type is invalid."` from the `Rule::in([...])` validator on `POST /api/v1/auth/higher-access` (or whichever route the POS hits — the controller is `App\Http\Controllers\API\v1\pos\HigherAccessController`).

**Repo:** `apex_backend` (Laravel)

---

## Step 1 — Branch

```bash
cd apex_backend
git checkout main          # or your trunk
git pull
git checkout -b feature/credit-payment-higher-access
```

---

## Step 2 — Decide the approver gate

The role column `roles.crdt_pymnt` already exists (it's what gates the cashier himself — the POS hides "Credit Payment" from cashiers whose role lacks it… *used* to hide; the POS now always shows it and falls back to the higher-access flow when missing).

You have two choices for the approver check:

- **Option A (recommended for symmetry & simplicity):** Reuse `crdt_pymnt`. "If you can do credit payments yourself, you can approve a request from someone who can't." This matches how `discounts`, `rfnd`, `delete_items`, `csh_out`, and `crdt_sale` already work — a single column for both "can do" and "can approve."
- **Option B (more granular, matches `locked_unit`):** Add a new column `roles.crdt_pymnt_approve` via migration. Mirrors `unit_lock` (do) vs `unit_lock_approve` (approve). Requires a small migration and a roles-admin UI update on the dashboard side.

The rest of this spec assumes **Option A** — substitute `crdt_pymnt_approve` for `crdt_pymnt` in the snippets below if you go with B and create the column.

---

## Step 3 — Update `HigherAccessController` (POS-facing)

File: `app/Http/Controllers/API/v1/pos/HigherAccessController.php`

### 3.1 — Add `credit_payment` to the validator

```php
'permission_type' => ['required', Rule::in([
    'discounts', 'refunds', 'delete_items', 'cash_out',
    'credit_sale', 'locked_unit',
    'credit_payment', // <-- new
])],
```

### 3.2 — Add the FCM permission mapping

In `notifyHigherAccessRequest()` extend the `match`:

```php
$permission = match ($accessRequest->permission_type) {
    'discounts' => 'discounts',
    'refunds' => 'rfnd',
    'delete_items' => 'delete_items',
    'cash_out' => 'csh_out',
    'credit_sale' => 'crdt_sale',
    'locked_unit' => 'unit_lock_approve',
    'credit_payment' => 'crdt_pymnt', // <-- new (Option A)
    default => null,
};
```

This is what `FcmService::sendToUsersWithPermission` reads to decide which approver users receive the push.

### 3.3 — Add the approve-gate mapping

In `canApprove()` extend the `match`:

```php
return match ($permissionType) {
    'discounts' => (bool) $role->discounts,
    'refunds' => (bool) $role->rfnd,
    'delete_items' => (bool) $role->delete_items,
    'cash_out' => (bool) $role->csh_out,
    'credit_sale' => (bool) $role->crdt_sale,
    'locked_unit' => (bool) $role->unit_lock_approve,
    'credit_payment' => (bool) $role->crdt_pymnt, // <-- new (Option A)
    default => false,
};
```

---

## Step 4 — Update `Admin\AccessRequestController` (dashboard-facing)

File: `app/Http/Controllers/Admin/AccessRequestController.php`

There are **three** parallel maps here — keep them in sync so the dashboard's approver list, gate, and label all show the new type correctly.

### 4.1 — `approvableTypesFor()`

```php
return collect([
    'discounts' => $role->discounts,
    'refunds' => $role->rfnd,
    'delete_items' => $role->delete_items,
    'cash_out' => $role->csh_out,
    'credit_sale' => $role->crdt_sale,
    'locked_unit' => $role->unit_lock_approve,
    'credit_payment' => $role->crdt_pymnt, // <-- new
])->filter()->keys()->values()->all();
```

### 4.2 — `canApprove()`

```php
return match ($permissionType) {
    'discounts' => (bool) $role->discounts,
    'refunds' => (bool) $role->rfnd,
    'delete_items' => (bool) $role->delete_items,
    'cash_out' => (bool) $role->csh_out,
    'credit_sale' => (bool) $role->crdt_sale,
    'locked_unit' => (bool) $role->unit_lock_approve,
    'credit_payment' => (bool) $role->crdt_pymnt, // <-- new
    default => false,
};
```

### 4.3 — `permissionLabel()`

```php
return match ($type) {
    'discounts' => 'Apply Discount',
    'refunds' => 'Process Refund',
    'delete_items' => 'Delete Item',
    'cash_out' => 'Void Cash Out',
    'credit_sale' => 'Credit Sale',
    'locked_unit' => 'Use Locked Unit',
    'credit_payment' => 'Receive Credit Payment', // <-- new
    default => ucwords(str_replace('_', ' ', $type)),
};
```

(Must match the displayName used on the POS — see `apex_pos/lib/models/higher_access_request_model.dart`'s `HigherAccessPermissionType.creditPayment` → `'Receive Credit Payment'`.)

---

## Step 5 — Flush OPCache after the edits

Sail keeps OPCache enabled on the web container, so the live server will keep serving the old code until you restart:

```bash
docker restart <project>_laravel.test_1
```

(`apex_backend-laravel.test-1` if you're on standard Sail naming.) Alternatively `./vendor/bin/sail artisan optimize:clear` clears Laravel's own caches but **does NOT** flush OPCache — only a container restart (or hitting the FPM-via-web `opcache_reset()`) does that. Don't trust `php -r 'opcache_reset();'` from `sail artisan tinker` either; the CLI runtime has its own OPCache namespace.

---

## Step 6 — Tests

Mirror the existing higher-access tests if any exist. At minimum:

- `POST /api/v1/auth/higher-access` with `permission_type: credit_payment` returns 200 (not 422 with "The selected permission type is invalid").
- The created `HigherAccessRequest` row stores `permission_type = 'credit_payment'`.
- An approver whose role has `crdt_pymnt = 1` passes `canApprove()`.
- An approver whose role has `crdt_pymnt = 0` is rejected with 403 / "You do not have permission to approve this request" on the admin approve endpoint.
- The FCM helper is called with the `'crdt_pymnt'` permission string (you can stub `FcmService::sendToUsersWithPermission` and assert the args).

---

## What does NOT change

- The polling endpoint, the `HigherAccessRequest` model/migration, `expires_at` logic, status enum — all unchanged.
- Existing six permission types — unchanged.
- The `users.user_id` (business owner) lookup used by FCM — unchanged.

---

## Rollout

Backward-compatible. Old POS builds without this feature won't ever send `credit_payment`, so the new branch in the `match`/`Rule::in` is dead weight for them. Old dashboard builds without Section 4 changes will simply not list the new type in their approvable filter — approvers won't see incoming credit_payment requests until the dashboard is updated, but the POS request itself will succeed and stay pending until expiration. Ship at your normal cadence; coordinate dashboard rollout if you care about end-to-end approvals working day 1.

---

## Cross-reference

- POS side enum: `apex_pos/lib/models/higher_access_request_model.dart` — `HigherAccessPermissionType.creditPayment` → `'credit_payment'`, displayName `'Receive Credit Payment'`.
- POS site of use: `apex_pos/lib/pages/menu_list.dart` — drawer's "Credit Payment" entry now always visible, fires `promptHigherAccessOption(role: 'credit_payment', …)` when `user.role?.crdtPymnt != 1`.
- Existing role column: `roles.crdt_pymnt` (already populated; no migration needed under Option A).
- Existing pattern to mirror: how `locked_unit` was wired (commit ref / file diff if you have it handy).
