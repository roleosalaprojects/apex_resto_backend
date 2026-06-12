# Cashless Payment Recording (Web Admin → Sale Conversion)

**Status:** Draft for review · 2026-06-06
**Branch:** to be created — `feature/cashless-payment-recording`
**Stakeholders:** owner (single-tenant for now; multi-tenant safe)

---

## 1. Goals

1. Let an authorized admin record a payment for an Ecommerce Order without it ever touching a POS terminal.
2. The moment payment is recorded, the order is **converted into a Sale row** (not left as a pending order), so it counts in every existing sales report by virtue of the same query path POS sales travel.
3. Differentiator: `sales.pos_id IS NULL` ⇒ recorded by web admin. `sales.pos_id NOT NULL` ⇒ rung up at a POS.
4. Add two new payment methods: **bank transfer** and **cheque**.
5. Reuse the existing Sale-creation pipeline POS uses — no parallel/divergent path. Same stock deduction, same points accrual, same `ecommerce_order_id` linkage, same `pos_logs` (or its admin-side equivalent).
6. Do not break Z-readings (they already filter by `pos_id = reading.pos_id`, so NULL-pos sales correctly stay out).

---

## 2. Non-goals

- Split payments. POS doesn't support them today; we don't introduce them here.
- Refunds of admin-recorded sales (treated as a follow-up).
- Mobile/Flutter changes. Everything new lives in apex_backend.
- New permissions UI. We gate on an existing role flag (see §7).
- Customer self-pay (Stripe / PayMongo / etc.). Out of scope; this is admin entering an already-received payment.

---

## 3. Current state (verified, with file refs)

### 3.1 What already exists and works

| Piece | Where | Notes |
|---|---|---|
| `sales.payment_type` | `database/migrations/2025_06_26_140938_add_payment_type_to_sales_table.php:16` | `1=Cash, 2=E-Wallet, 3=Credit Sale`. Stored as int, sent as string from POS. |
| `sales.ecommerce_order_id` | `database/migrations/2026_01_28_100000_add_ecommerce_order_id_to_sales_table.php` | FK already in place. POS already passes it in create-sale. |
| `sales.bank_id` + `sales.bank_amount` + `sales.reference_number` | `app/Models/Pos/Sale.php:22-79` fillable | POS uses these for e-wallet today. We reuse them for bank transfer + cheque. |
| Sale create endpoint | `POST /api/v1/sales` → `app/Http/Controllers/API/v1/pos/SaleController.php::store` (line 45) | `StoreRequest` validation, `DB::transaction`, `UpdateItemStocksJob::dispatch`, points accrual, credit ledger, vouchers, PosLog. |
| Stock deduction | `app/Jobs/API/v1/Sale/UpdateItemStocksJob.php:32-76` | Keyed on `sale.store_id`. Orders DO NOT pre-deduct, so converting an order to a sale deducts exactly once. ✓ |
| Order has FK to Sale | `EcommerceOrder::sale()` HasOne | `isFulfilled()` returns true iff Sale row exists with this `ecommerce_order_id`. We don't need a separate `converted_at` column — the FK relation IS the source of truth. |
| Reports filter | `app/Services/ReportService.php:91-114` | Filters on `user_id`, `cancelled`, `type`, optionally `store_id`. **Does not filter on `pos_id`** → admin-recorded sales surface in dashboards/reports automatically. ✓ |
| POS-side conversion | `EcommerceOrderController::cartData` + Flutter app | POS can already load an order into the cart and create a Sale with `ecommerce_order_id` populated. The new admin flow is *parallel to* this, not replacing it. |

### 3.2 What is missing

| Missing | Why it matters |
|---|---|
| `sales.pos_id` is currently `NOT NULL` (`database/migrations/2020_10_17_023843_create_sales_table.php:31`) | Blocks admin-recorded sales. Must become nullable. |
| Payment methods 4 (Bank Transfer) and 5 (Cheque) | Owner wants these as distinct rails, not lumped under e-wallet. |
| Admin endpoint that calls the Sale pipeline with `pos_id = NULL` | Doesn't exist today. |
| Admin UI to enter payment details against an order | Doesn't exist today. |
| Z-reading / pos_log handling for admin sales | PosLog assumes a `pos_id`. We need a clean "no-op" or an admin-side audit row. |

---

## 4. Architecture decisions

### 4.1 Conversion model: **Order → Sale (forward-only)**

When admin records payment:

```
EcommerceOrder (status=1, fulfilled via FK)
       │
       └──[Sale row created with ecommerce_order_id=order.id, pos_id=NULL]
                │
                ├── SaleLines copied from EcommerceOrderLines (at current item.cost for accurate profit)
                ├── UpdateItemStocksJob dispatched → stock deducts
                ├── Points accrued on customer (same logic as POS)
                └── EcommerceOrder.status set to 1 (verified) if still 0
```

The order row stays. We do not delete it, do not duplicate it, do not flip it to a fake "paid" status. `order.isFulfilled()` becomes `true` because `order.sale` now exists. This matches how POS-converted orders already behave today.

**Why not add a `converted_at` / `paid_at` to EcommerceOrder?**
The Sale FK already answers "is this order fulfilled" and "when" (`sale.created_at`). Adding parallel columns creates two sources of truth that drift.

### 4.2 Pipeline reuse: **shared service, two callers**

Extract the Sale-creation logic that currently lives inside `SaleController::store` into a service:

- `app/Services/Pos/SaleCreationService.php`
- Method: `create(SaleCreationData $data): Sale`
- Both callers feed it the same DTO:
  - **POS caller** (existing `SaleController::store`) → `pos_id = $request->pos_id`, payload from Flutter request
  - **Admin caller** (new `RecordOrderPaymentController::store`) → `pos_id = null`, payload assembled from the EcommerceOrder + admin form

This is the only safe way to make sure admin-recorded sales hit the *exact same* observers, jobs, points, and inventory rules. A parallel `createAdminSale` method would drift the day after we merge.

**Refactor risk:** SaleController::store is large. We extract incrementally: pull out the "happy path" first, leave POS-specific concerns (z_reading association, pos_log writing) as caller responsibilities. Tested before and after with the existing POS test suite.

### 4.3 Differentiator: `pos_id IS NULL`

No new column, no enum, no `source` field. The presence of `pos_id` answers "was this rung up at a terminal?". Per owner: *"if pos_id is null it means it was paid by web admin."*

Reports that want to slice by source can add `whereNull('pos_id')` / `whereNotNull('pos_id')` later. Today, no report needs the split.

### 4.4 New payment methods

Add to the existing `payment_type` int enum:

| Value | Slug | Already exists? | Bank FK required? | Reference required? |
|---|---|---|---|---|
| 1 | Cash | yes | no | no |
| 2 | E-Wallet (GCash etc.) | yes | yes (`banks.id`) | yes (ref no.) |
| 3 | Credit Sale | yes | no | no |
| **4** | **Bank Transfer** | new | yes (`banks.id`) | yes (transfer ref) |
| **5** | **Cheque** | new | yes (`banks.id` — drawee bank) | yes (cheque no.) |

We re-use `sales.bank_id`, `sales.bank_amount`, `sales.reference_number` — no new columns. The frontend distinguishes the UX (cheque collects cheque number, bank transfer collects transfer reference), but persistence is identical.

A constants file or PHP enum surfacing these would be a nice cleanup but is **not blocking**. For this feature we add the 2 values via migration comment update + use `int` literals where they already appear. (Enum refactor can be a separate PR.)

### 4.5 Tenancy

- `EcommerceOrder` doesn't carry `user_id` directly — it's derived through `customer.user_id`.
- The created Sale row carries `user_id` (tenant owner). It must match `$order->customer->user_id`, NOT the admin's own id (admin may or may not equal tenant in multi-tenant setups).
- The admin recording the payment is stamped via `sales.sales_by = auth()->id()` (mirroring POS where `sales_by` is the cashier).
- Authorization gate: admin must belong to the same tenant as the order's customer. Concretely: `auth()->user()->user_id === $order->customer->user_id` (single-tenant today, but the check is cheap and future-proofs).

### 4.6 Z-Reading & pos_logs

- Z-Reading: `ZreadingController` reads `pos_id = $reading->pos_id` — NULL doesn't match any reading. ✓ Admin sales correctly never appear in any Z-reading.
- pos_logs: a Sale created without a POS shouldn't generate a `pos_log` row (pos_log is the per-terminal audit stream). We do, however, want an audit trail.
  - Decision: skip `PosLogJob` for admin sales. Add a row to `audit_logs` instead, scoped to model `EcommerceOrder` event `payment_recorded` with the Sale id in the diff.
  - This honors the existing memory rule: *don't audit Sale itself; audit_logs is for financial/config/inventory-metadata models* — `EcommerceOrder` is a financial event.

---

## 5. Data layer changes

### 5.1 Migration: make `pos_id` nullable

`database/migrations/2026_06_06_000000_make_pos_id_nullable_on_sales_table.php`

```php
Schema::table('sales', function (Blueprint $table) {
    $table->unsignedInteger('pos_id')->nullable()->change();
});
```

Down: re-NOT-NULL only if no NULL rows exist (guard the rollback so we don't error mid-rollback in environments that already recorded admin sales).

### 5.2 Migration: update `payment_type` comment

`database/migrations/2026_06_06_000001_extend_payment_type_on_sales_table.php`

```php
Schema::table('sales', function (Blueprint $table) {
    $table->unsignedInteger('payment_type')
          ->default(1)
          ->comment('1=Cash, 2=E-Wallet, 3=Credit, 4=Bank Transfer, 5=Cheque')
          ->change();
});
```

No data migration needed — existing rows stay at their existing values.

### 5.3 No new columns

Reusing `bank_id`, `bank_amount`, `reference_number`, `ecommerce_order_id`. Reusing `sales_by` for the recording admin.

### 5.4 Models

- `Sale`: no schema change; update PHPDoc on `payment_type` to list new values.
- `EcommerceOrder`: no change.

---

## 6. Service layer

### 6.1 New: `App\Services\Pos\SaleCreationService`

```php
final class SaleCreationService
{
    public function __construct(
        protected WholesalePricingService $pricing,
        // ... existing deps from SaleController
    ) {}

    public function create(SaleCreationData $data): Sale
    {
        return DB::transaction(function () use ($data) {
            $sale = Sale::create($data->saleAttributes());
            SaleLine::insert($data->saleLineRows($sale->id));

            $this->awardPoints($sale, $data);
            $this->writeCreditLedger($sale, $data);  // if payment_type = 3
            $this->writeBankDeposit($sale, $data);   // if payment_type in [2,4,5]

            UpdateItemStocksJob::dispatch($sale);

            return $sale->fresh(['lines']);
        });
    }
}
```

`SaleCreationData` is a read-only DTO (constructor-promoted properties) holding everything the create needs, source-agnostic. Two factories build it:

- `SaleCreationData::fromPosRequest(StoreRequest $request, int $posId): self`
- `SaleCreationData::fromOrder(EcommerceOrder $order, RecordPaymentRequest $request): self`

The POS controller becomes a thin wrapper that constructs the DTO from its request, calls the service, then handles POS-only concerns (PosLog, response shaping).

### 6.2 New: `App\Services\Ecommerce\RecordOrderPaymentService`

Orchestrates the admin path:

```php
public function record(EcommerceOrder $order, RecordPaymentRequest $req, User $admin): Sale
{
    $this->authorize($order, $admin);             // tenancy check
    $this->guardAlreadyFulfilled($order);          // refuse if order.sale exists
    $this->guardCancelled($order);                 // refuse if status = 2

    $data = SaleCreationData::fromOrder($order, $req);
    $sale = $this->saleCreation->create($data);

    if ($order->status === 0) {
        $order->update(['status' => 1, 'verified_by' => $admin->id, 'verified_at' => now()]);
    }

    AuditLog::record(
        modelType: EcommerceOrder::class,
        modelId: $order->id,
        event: 'payment_recorded',
        diff: ['sale_id' => $sale->id, 'payment_type' => $req->payment_type],
    );

    return $sale;
}
```

---

## 7. HTTP layer

### 7.1 Route

`routes/admin.php` — add:

```php
Route::post(
    'ecommerce-orders/{ecommerceOrder}/record-payment',
    [EcommerceOrderController::class, 'recordPayment'],
)->name('admin.ecommerce-orders.record-payment');
```

Middleware: `auth` + a gate `record-cashless-payment` that checks role flag (see §7.3).

### 7.2 Form Request

`app/Http/Requests/Admin/Ecommerce/RecordOrderPaymentRequest.php`

```php
public function rules(): array
{
    return [
        'payment_type'     => ['required', 'integer', Rule::in([1, 2, 4, 5])], // no 3 (credit) — admin path is for received payment
        'store_id'         => ['required', 'integer', 'exists:stores,id'],
        'bank_id'          => ['nullable', 'integer', 'exists:banks,id',
                               'required_if:payment_type,2,4,5'],
        'reference_number' => ['nullable', 'string', 'max:120',
                               'required_if:payment_type,2,4,5'],
        'bank_amount'      => ['nullable', 'numeric', 'min:0',
                               'required_if:payment_type,2,4,5'],
        'note'             => ['nullable', 'string', 'max:500'],
    ];
}
```

`store_id` is required so stock deducts from the right store. The form defaults it to the admin's primary store but always exposes the picker. **Open question — see §10.**

### 7.3 Authorization

Add a gate in `app/Providers/AuthServiceProvider.php`:

```php
Gate::define('record-cashless-payment',
    fn (User $user) => (bool) ($user->sls_create ?? false));
```

We reuse `sls_create` (the existing "can create sales" flag) — it's already on roles, already enforced for the POS app, and conceptually "record a payment that becomes a sale" is the same authority. **Open question — see §10.**

### 7.4 Controller method

`app/Http/Controllers/Admin/Ecommerce/EcommerceOrderController::recordPayment`

```php
public function recordPayment(
    EcommerceOrder $ecommerceOrder,
    RecordOrderPaymentRequest $request,
    RecordOrderPaymentService $service,
): RedirectResponse {
    $sale = $service->record($ecommerceOrder, $request, auth()->user());

    return redirect()
        ->route('admin.ecommerce-orders.show', $ecommerceOrder->id)
        ->with('success', "Payment recorded as Sale #{$sale->son}.");
}
```

---

## 8. UI changes

### 8.1 `/admin/ecommerce-orders/{id}` show page

- Add a **"Record Payment"** button visible iff:
  - `order.status` ∈ {0, 1}
  - `! order.isFulfilled()`
  - current user passes `record-cashless-payment` gate
- Clicking opens a Bootstrap modal with the form:
  - Payment method radio buttons: **Cash · GCash/E-Wallet · Bank Transfer · Cheque**
  - When method ≠ Cash: show bank picker (Banks owned by tenant), reference number, amount received fields
  - Store picker (defaulted)
  - Optional note
  - Confirm button
- On success: redirect back to show page, flash success, page now displays "Fulfilled — Sale #SO-XXXX (Bank Transfer, ref 123456)".

### 8.2 `/admin/ecommerce-orders` index page

Add a column / badge variant:
- Pending (gray)
- Verified — awaiting POS (yellow)
- **Verified — paid via web admin** (new green variant)
- Fulfilled at POS (green)
- Cancelled (red)

Drives off `pos_id IS NULL` vs `NOT NULL` on the linked sale.

### 8.3 Sale show page (existing)

If `pos_id IS NULL`, show a small "Recorded via web admin" badge near the POS terminal name. One-line view tweak only.

---

## 9. Test plan

### 9.1 Unit / service tests

- `SaleCreationServiceTest`
  - creates sale with `pos_id = null`
  - dispatches `UpdateItemStocksJob` once
  - awards points using existing `points_multiplier`
  - writes bank deposit row when payment_type ∈ {2, 4, 5}
- `RecordOrderPaymentServiceTest`
  - refuses if order already has a Sale
  - refuses if order is cancelled (status=2)
  - sets `EcommerceOrder.status = 1` if it was 0
  - tenancy guard: admin from different tenant gets `AuthorizationException`
  - audit_logs row written with sale_id

### 9.2 Feature tests

- `tests/Feature/Admin/EcommerceOrderRecordPaymentTest.php`
  - cash payment: 200 OK, sale created, no bank fields populated
  - bank transfer payment: 200 OK, sale.bank_id/amount/ref populated
  - cheque payment: same shape as bank transfer
  - invalid payment_type=3 (credit) → 422
  - missing reference on bank transfer → 422
  - already-fulfilled order → 422
  - cancelled order → 422
  - unauthorized user (no `sls_create` role flag) → 403
  - cross-tenant admin → 403
  - reports query that aggregates today's sales **includes** the new admin sale (regression guard for §3.1 ReportService)

### 9.3 Regression guards (existing tests must still pass)

- `tests/Feature/API/v1/Pos/SaleStoreTest.php` (or whatever covers POS sale create)
- Z-Reading tests must show admin sales are excluded
- pos_logs feature tests must show no pos_log row written for admin sales

### 9.4 Manual smoke

- Create an ecommerce order via `/shop`, log into admin, record payment via cheque
- Check inventory deducted exactly once
- Check sales report includes the row
- Check Z-Reading does NOT include the row
- Check apex_pos still works for its own create-sale flow (no regression)

---

## 10. Locked decisions (owner review 2026-06-06)

### Q1. Role gate — **LOCKED: reuse `sls_create`**
Gate `record-cashless-payment` returns `(bool) $user->sls_create`. No new role bit. If the owner ever wants a tighter split, we add `record_cashless` later — the gate name stays the same so callers don't change.

### Q2. Store selection — **LOCKED: admin store picker every time**
The modal always shows a store dropdown (Banks/Stores owned by tenant). Default the dropdown to the admin's primary store, but admin must explicitly confirm. Rationale: in multi-store tenants the admin is making a real fulfilment decision ("this got picked up at the Cebu branch") that affects stock attribution.

### Q3. Customer points — **LOCKED: yes, accrue same as POS**
Admin-recorded sales pass through `awardPoints()` in `SaleCreationService` exactly like POS sales. No checkbox in the modal — owner can always reverse a points entry manually if needed.

### Q4. Cheque clearing workflow — **LOCKED: pending → cleared/bounced**

Bank deposit is created **immediately** for every payment_type ∈ {2, 4, 5}, but with a clearing state. Cheques start pending; cash/GCash/bank-transfer start cleared.

**Schema:** add `bank_deposits.status` enum with three values:

| Status | Meaning | Counted in bank balance? |
|---|---|---|
| `cleared` | money is in the bank — default for cash (1), e-wallet (2), bank transfer (4) | yes |
| `pending` | cheque written, not yet cleared at drawee — default for cheque (5) | no |
| `bounced` | drawee bank refused — terminal state for a bad cheque | no |

If `bank_deposits.status` doesn't exist yet (verify in implementation PR), the migration adds it with default `cleared` so all existing rows backfill correctly.

**New surface:** `/admin/cheques/pending` index page (Yajra DataTable, items-style convention) showing every `pending` cheque deposit with:
- Cheque #, customer, drawee bank, amount, days outstanding (highlight red if > 30), linked Sale
- Two row actions:
  - **Mark Cleared** → opens modal asking for clearing date (defaults today) and an optional clearing-bank reference. On confirm: status → `cleared`, audit_log entry, the bank balance views now reflect it.
  - **Mark Bounced** → opens confirm modal explaining the irreversible nature. On confirm: status → `bounced`, audit_log entry, AND a `customer_credit_transactions` charge entry is created so the customer's balance shows they still owe the amount. The original Sale row stays untouched (the goods left the warehouse — the customer still received them; they just didn't pay).
- Bulk action: select multiple cheques → "Mark all cleared" with one shared clearing date (common when admin deposits a batch).

**Bank balance / reconciliation queries** must filter `bank_deposits.status = 'cleared'` to compute available balance. Pending and bounced deposits are visible but uncounted.

**Optional later:** scheduled command nudges admin via email if a cheque has been `pending` more than N days (default 14, configurable per tenant in Settings). Not in this PR — flagged as follow-up.

### Q5. Refunds of admin-recorded sales — **LOCKED: follow-up PR**
This PR hides the existing refund button on the Sale show page when `pos_id IS NULL`. We address admin-side refunds in a dedicated PR once we have real usage to inform the UX (probably a reverse-flow that creates a negative Sale row with `type = 1`).

### Q6. Audit scope — **LOCKED: audit on BOTH `EcommerceOrder` AND `Sale`**

This is an explicit, narrow override of the existing memory rule (`feedback_audit_scope.md`) that says "don't audit Sale". Scoped exception:

- POS-originated sales (`pos_id NOT NULL`) → audit stays OFF. `pos_logs` remains the source of truth as before. No change.
- Admin-recorded sales (`pos_id IS NULL`) → audit goes ON for BOTH the `EcommerceOrder` event AND the `Sale` event, because `pos_logs` is empty for them and the owner wants every step traceable.

Concrete writes per admin recording:
1. `audit_logs` row on `EcommerceOrder` event `payment_recorded` with `{sale_id, payment_type, bank_id, reference_number, store_id}` in the diff.
2. `audit_logs` row on `Sale` event `created_via_admin` with `{ecommerce_order_id, payment_type, store_id, total}` in the diff.

Cheque clearing/bouncing also writes:
3. `audit_logs` row on `BankDeposit` event `cheque_cleared` or `cheque_bounced` with `{cleared_at, reference, by_user_id}`.

After this spec is approved I'll update `~/.claude/projects/.../memory/feedback_audit_scope.md` so future me doesn't undo this — the rule becomes "don't audit POS-originated Sale; DO audit admin-originated Sale + Order + BankDeposit for cashless flow."

---

## 10b. Z-Reading vs Daily Summary (added 2026-06-06)

### Constraint
Z-Reading is BIR-mandated for POS terminals in the Philippines. We cannot drop it. It is the per-terminal, per-shift cash reconciliation required for tax filing. Decision: **keep Z-Reading exactly as it is; add Daily Summary as a separate operational layer.**

### Two clean lanes

| Surface | Scope | Audience | Status |
|---|---|---|---|
| **Z-Reading** | per-POS-terminal, per-shift | cashier (closing till), BIR (tax) | unchanged |
| **Daily Summary** | tenant-wide, per-day, all stores + admin sales + cheques + bank movements | owner (operational view) | NEW |

Admin-recorded sales (`pos_id IS NULL`) correctly never appear in Z-readings — the existing `pos_id = $reading->pos_id` filter already excludes them. No code change to Z-readings.

### Daily Summary deliverables (this PR or follow-up — see §11)

**New surface:** `/admin/reports/daily` showing:
- Date picker (defaults today)
- **POS sales total** (broken down per store, per cashier)
- **Admin cashless sales total** (broken down per payment_type: cash / e-wallet / bank transfer / cheque)
- **Pending cheques aging** (count + amount, link to `/admin/cheques/pending`)
- **Bank deposits today** (cleared only — filter `status = 'cleared'` AND `deposited_at = today`)
- **Total gross**, **total profit**, **total refunds**, **net cash position**
- Compare-to-previous-day toggle
- Export to CSV

**Scheduled email:** new command `report:daily-summary` (built on the existing `report:generate` infrastructure per memory) emails the above to each tenant's report recipients every morning at 06:00 local time for yesterday's totals. Honors per-tenant timezone.

### Recommended split
- **This PR (cashless payment recording):** ships Z-Reading unchanged, ships the cheque clearing surface (since that's load-bearing for cashless), but does NOT ship Daily Summary.
- **Next PR (daily summary):** ships the `/admin/reports/daily` view and `report:daily-summary` scheduled command. Has its own spec.

Reason: this PR is already moderately large (refactor + feature). Bundling Daily Summary makes the diff harder to review and delays the cashless feature shipping. Daily Summary's value is independent — it's useful even without cashless.

**Owner decision (2026-06-06): SKIP Z-Reading entirely from this work** — owner is researching BIR Sales Invoice + submission flow separately. Daily Summary and everything else gets built together on a single feature branch `feature/cashless-payment-recording`. The internal commit split (refactor → migrations → service → UI → cheque flow → daily summary → tests) is kept so the diff is readable; at merge time owner decides whether to squash, split into multiple PRs, or merge as-is.

---

## 11. Rollout

1. **PR 1 — refactor only, no behavior change:** extract `SaleCreationService` from `SaleController::store`. All existing POS tests green. Zero functional change visible to users or the Flutter app.
2. **PR 2 — cashless feature:** migrations (pos_id nullable, payment_type comment extended, bank_deposits.status enum) + admin record-payment endpoint + cheque clearing surface (`/admin/cheques/pending`) + UI + audit logs + tests. Behind no feature flag — the role gate (`sls_create`) governs visibility.
3. **PR 3 — daily summary (separate spec):** `/admin/reports/daily` view + `report:daily-summary` scheduled command. Independent of cashless feature; ships when ready.
4. Manual smoke per §9.4 after each PR.
5. Merge to dev, manual QA on dev, merge to main.

No backfill, no data migration, no apex_pos coordination needed — the POS app already sends `ecommerce_order_id` and accepts the new payment_type ints (it'll just never originate 4 or 5 itself). Z-Reading flow is fully untouched.

---

## 12. Files touched (summary)

**New (PR 2):**
- `database/migrations/2026_06_06_000000_make_pos_id_nullable_on_sales_table.php`
- `database/migrations/2026_06_06_000001_extend_payment_type_on_sales_table.php`
- `database/migrations/2026_06_06_000002_add_status_to_bank_deposits_table.php`
- `app/Services/Pos/SaleCreationService.php`
- `app/Services/Pos/Data/SaleCreationData.php`
- `app/Services/Ecommerce/RecordOrderPaymentService.php`
- `app/Services/Banking/MarkChequeClearedService.php`
- `app/Services/Banking/MarkChequeBouncedService.php`
- `app/Http/Requests/Admin/Ecommerce/RecordOrderPaymentRequest.php`
- `app/Http/Requests/Admin/Banking/MarkChequeClearedRequest.php`
- `app/Http/Controllers/Admin/Banking/PendingChequeController.php`
- `resources/views/admin/banking/cheques/pending/index.blade.php`
- `tests/Unit/Services/Pos/SaleCreationServiceTest.php`
- `tests/Unit/Services/Ecommerce/RecordOrderPaymentServiceTest.php`
- `tests/Unit/Services/Banking/MarkChequeClearedServiceTest.php`
- `tests/Feature/Admin/EcommerceOrderRecordPaymentTest.php`
- `tests/Feature/Admin/PendingChequeFlowTest.php`

**Modified (PR 2):**
- `app/Http/Controllers/API/v1/pos/SaleController.php` (thin wrapper around new service)
- `app/Http/Controllers/Admin/Ecommerce/EcommerceOrderController.php` (add `recordPayment`)
- `routes/admin.php` (record-payment route + pending-cheque routes)
- `app/Providers/AuthServiceProvider.php` (new gate)
- `resources/views/admin/ecommerce/orders/show.blade.php` (Record Payment button + modal)
- `resources/views/admin/ecommerce/orders/index.blade.php` (cashless-paid badge variant)
- `resources/views/admin/sales/show.blade.php` (hide refund button when pos_id IS NULL; "Recorded via web admin" badge)
- `app/Models/Pos/Sale.php` (PHPDoc on payment_type)
- `app/Models/Banking/BankDeposit.php` (status fillable + casts + scope `cleared()`)
- bank balance / reconciliation queries — audit all callers, add `where('status', 'cleared')` filter

**Memory file to update (after spec approval):**
- `~/.claude/projects/-Users-richardleosala-Projects-RLCPS-apex-backend/memory/feedback_audit_scope.md` — narrow the no-audit-on-Sale rule to POS-originated sales only; document the admin-Sale exception.
