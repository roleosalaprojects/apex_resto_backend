# Multi-Tenancy Enforcement — Plan (deferred)

**Status:** Planning document. Not yet scheduled for implementation.
**Discussed:** 2026-05-11
**Owner decision:** Defer. Revisit when ready.

---

## Why this is on the table

The codebase has always had a multi-tenant data shape — every domain table carries a `user_id` that points at a tenant-owner User (`users.id == users.user_id`). But the application code has been loose about honoring that boundary. The OpenClaw bot work made this visible because `api_tokens.user_id` is *deliberately* tenant-scoped — you can't issue a token that crosses tenants — which forced the question: if tokens know about tenancy, why doesn't everything else?

The owner's framing: *"It all boils down to who owns who. We need to address this so we can shape where the app can go."*

## Confirmed architecture

```
Admin (admins table)                     ← platform moderators; multiple can exist
   │ onboards new businesses
   ▼
Tenant-owner User (users.id == users.user_id)   ← one row per tenant business
   │ owns all of:
   ├── Employees (users.user_id = owner.id)
   ├── ApiTokens (api_tokens.user_id = owner.id)   ← bot tokens, per-tenant
   └── Resources: Items, Sales, Stores, Banks,
                  Customers, Vouchers, Expenses,
                  Suppliers, etc.   (all .user_id = owner.id)
```

**Key facts confirmed by the owner:**

- Superadmins are platform **moderators**, not owners of any particular tenant. Their job is onboarding.
- A Superadmin onboards a tenant by going to `/superadmin/admin/create`. This already kicks off a full tenant bootstrap: default Business name, TIN, Address, Owner role, BIR role, Bagger role, BIR Officer user, Employee record, Tax (12% VAT), Category (`NO CATEGORY`), Store (`DEFAULT STORE`), walk-in Customer, default Supplier, PosSetting — all created in one transaction by `App\Http\Controllers\SuperAdmin\UserController::store`.
- After onboarding, the Superadmin walks away. **No persistent FK is needed** linking a tenant back to the Superadmin who created them — onboarding is a one-shot operation.
- **1:N**: a single Superadmin can onboard many tenants.
- **Receipt Settings** (`/superadmin/receipt`) is **platform-level branding** (the SaaS provider's company name on every printed receipt across every tenant). Not per-tenant.

## What's broken today

- Multiple admin/POS controllers route-model-bind resources by ID **without** checking ownership (`$model->user_id === currentTenantId()`). Cross-tenant IDOR — anyone with admin access to tenant A can read or modify tenant B's resources by guessing IDs. This is finding **H-01** from the 2026-05-11 security audit.
- `HomeController::default` queries Sales/Expenses with no tenant filter. The admin dashboard leaks aggregate data across tenants. Finding **H-09**.
- The `/superadmin/admin` page lists every User row including employees, when it should list only tenant owners (the actual tenants).
- The "who" in `created_by` is sometimes the tenant owner and sometimes the actor — inconsistent across the cash-out sync paths (already fixed in commit `49d8c5f`, but the pattern of "be explicit about whose action this is" applies elsewhere).
- The `auth()->user()->user_id` indirection is repeated dozens of times. One typo becomes a tenant-isolation bug.

## The plan, in tranches

Each tranche stands alone — pick any prefix and the codebase is still coherent.

### Tranche A — Tenant resolution infrastructure (no behavior change)

One PR introducing three small pieces of plumbing:

1. **`App\Support\CurrentTenant`** — a request-scoped helper. Single method: `id(): int` returns `auth()->user()->user_id`. Cached per request. Replaces every `auth()->user()->user_id` call site. One source of truth.

2. **`App\Models\Concerns\BelongsToTenant`** trait — adds an Eloquent local scope `forTenant($id)` (NOT a global scope; global scopes hide bugs). Also adds a `tenant()` BelongsTo relationship to the owner User. Apply to: `Item`, `Sale`, `Expense`, `Bank`, `BankTransaction`, `Voucher`, `Customer`, `Supplier`, `Store`, `Category`, `Tax`, `Purchase`, `ShopAnnouncement`, plus the new ones. Roughly 15 models.

3. **`App\Http\Controllers\Concerns\AuthorizesTenantOwnership`** trait — provides `assertOwnership(Model $m): void` that 404s if `$m->user_id !== CurrentTenant::id()`. Used at the top of every route-model-bound method.

**Tests:** unit tests for the trait + helper, no controller behavior change yet.

### Tranche B — `/superadmin` "Users" → "Tenants" rework (the bit the owner flagged)

`/superadmin/admin` currently lists every User. After this:

- Filter index to tenant-owner users only: `User::where('id', DB::raw('user_id'))`
- Rename page heading, breadcrumb, sidebar link: "Users" → "Tenants"
- Columns: Business name (resolved via default Store name or Receipt name), # employees, # stores, created_at, status
- Add-button text becomes "Create Tenant" (form already does the right thing — full bootstrap)
- No schema change

**Affects:** only the `/superadmin` UI. No client app impact.

### Tranche C — Controller IDOR cleanup (Tranche-2 of the security audit, now framed as tenant enforcement)

For each route-model-bound controller, add `assertOwnership($model)` at the top of show/edit/update/destroy. Splits into ~3 PRs by surface:

- **Admin web**: `ItemController`, `CustomerController`, `VoucherController`, `SpecialCustomerController`, `WholesalePriceTierController`, `ShopAnnouncementController`, `BankController`
- **POS API (v1)**: `SaleController::refundReceipt`, `CustomerCreditController::balance/payment`, `CustomerController::searchCustomers/customers/details`
- **Mobile API**: any route-model-bound endpoint

Same scope-everything fix for `HomeController::default` (H-09) and any dashboard widget that queries without `where('user_id', CurrentTenant::id())`.

**Tests:** for each fixed controller, add a "tenant A admin gets 404 on tenant B's resource" assertion. This is also how we verify we didn't accidentally over-scope.

**📱 Client impact** (apex_pos / apex_dashboard): clients today may receive cross-tenant rows in list endpoints (`/customers/search`, `/items/get`, etc.). After this, they'll see only their tenant. **Smoke-test path before/after**: list customers, search customers, list items, view sale by id, refund sale.

### Tranche D — Bot token integrity (small, one PR)

The bot is already tenant-scoped at token-creation time (`api_tokens.user_id` is the tenant owner) — but the enforcement is implicit. Make it explicit:

- `CheckOpenclawAbility` middleware: assert `CurrentTenant::id() === $token->user_id` directly (today this works through the guard but isn't articulated)
- OpenClaw endpoints that route-model-bind (`POST /openclaw/expenses/{id}/void`, etc.) gain `assertOwnership($model)` calls
- `docs/openclaw-api.md` updated to state explicitly that tokens are strictly per-tenant

**No client impact** — apex_pos doesn't use the bot API.

### Tranche E — Cross-tenant Superadmin operations (small, deserves separate discussion)

Two open questions to settle before implementing:

1. The `/superadmin` dashboard currently shows aggregate stats — does it intentionally aggregate across all tenants, or should it be filtered to "the tenants I'm currently managing"? (Today implicitly cross-tenant. May be the right behavior — moderators want a platform overview.)
2. Should there be a "view as tenant X" mode in `/superadmin` so a moderator can debug an issue without leaving the moderator role? Today `User::find($id)` already exists at the Eloquent level; question is whether to expose it via UI.

This tranche is more design than code. Defer until A–D are landed.

### Tranche F — Documentation

- README: "Multi-Tenancy" section explaining the ownership chain, what's per-tenant vs platform-level (Receipt Settings, audit log infrastructure)
- New `docs/multi-tenancy.md` for engineering reference:
  - The meaning of `user_id` on each model (it's the tenant owner's id, NOT the row creator's id — those are different concepts captured separately)
  - The `CurrentTenant::id()` helper and where to use it
  - The `assertOwnership()` pattern
  - The Superadmin → Tenant onboarding flow

## What this plan deliberately does NOT do

- **No `users.admin_id` column.** The owner clarified that Superadmin is a moderator role and onboarding is one-shot — there is no persistent need to track which Superadmin created which tenant. (Earlier draft of this plan proposed this; the owner overruled it.)
- **No global scopes.** Local scopes + explicit assertions are easier to read and harder to silently bypass.
- **No column rename (`user_id` → `tenant_id`).** The cost (touching ~50 models, controllers, client apps) is not justified by the readability gain.
- **No tenant-switcher in `/admin`.** The `/admin` panel always operates on the authenticated user's tenant. Cross-tenant moves happen at `/superadmin`.
- **No change to the public API surface.** apex_pos and apex_dashboard continue to call the same endpoints with the same response shapes. The change is that the backend stops returning data they shouldn't see.

## Client-app compatibility (apex_pos & apex_dashboard)

Both Flutter clients live at `~/Projects/RLCPS/apex_pos/` and `~/Projects/RLCPS/apex_dashboard/`. A quick scan on 2026-05-11 found:

- Endpoint surface (apex_pos): `/login`, `/items`, `/customers`, `/customers/search`, `/sales`, `/sales/void`, `/sales/refund`, `/readings`, `/x-reading`, `/z-reading`, `/zreadings`, `/pos-logs/*`, `/receipts`, `/auth/higher_access`, `/auth/higher-access/*`. None of these endpoint names change in any tranche.
- Both apps **read** `user_id` from JSON responses for display but don't filter by it client-side — they trust the backend to return only their tenant's data.
- apex_pos **sends** `user_id` in some request bodies (e.g., sales). The backend should derive this from `auth()` and ignore client-supplied values; this hardening is part of Tranche C anyway.

**Net assessment:** the tenancy work is transparent to the client apps as long as the API contracts hold. Tranche C is the only place where client-visible behavior could change (clients receiving less data than before). A pre-deploy smoke test on apex_pos's list/search endpoints is the right level of caution.

## Open questions to settle before starting

These don't block the planning, but they need answers before code lands:

1. **Tranche E (cross-tenant Superadmin operations)** — defer entirely, or schedule a dedicated discussion?
2. **Smoke-test scope before Tranche C cutover** — is dev/staging POS testing enough, or do we want explicit per-endpoint regression coverage?
3. **Naming of the existing default Store on a tenant** — today it's `DEFAULT STORE` for every tenant. Worth making this configurable during onboarding? (Not part of the tenancy plan, but the `/superadmin/admin/create` form change is a natural place to add the field.)

## Sequencing

A → B → C → D → (E?) → F

Each tranche is independently shippable. Total scope estimate: A is ~1 day, B is ~half a day, C is ~2-3 days spread across 3 PRs, D is ~half a day, E is undefined, F is ~half a day.

## What to revisit before scheduling

- Re-confirm the audit-log infrastructure still tracks bot vs. human attribution (per existing `audit_logs.api_token_id` + `source` columns). The tenancy work doesn't replace it.
- Verify that no recent feature added a model with `user_id` that isn't on the BelongsToTenant list above.
- Re-read this doc to make sure no architectural decisions have shifted in the meantime.
