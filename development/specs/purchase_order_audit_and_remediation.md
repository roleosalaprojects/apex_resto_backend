# Purchase Order — audit + remediation spec

## 0. Context

The Purchase Order feature is mid-flight between two payment regimes and three API surfaces. A spec to add Withholding Tax (EWT) on POs was written today (`development/specs/ewt_purchase_order_spec.md`), but layering new tax math on top of the current bugs would compound the corruption. This document is the punch list of everything that needs to land BEFORE EWT work begins.

Source of the findings: two parallel deep-dive audits run 2026-06-09 against `apex_backend` HEAD (`bd971ed` on `dev`) and `apex_dashboard` HEAD.

---

## 1. Tier 1 — silent data corruption / concurrency (fix first)

### 1.1 Typo `ammount` (silent column write to a nonexistent field)

- **File**: `app/Http/Controllers/Admin/InventoryManagement/PurchaseController.php:605, 615`
- **Method**: `savePaymentDetails()`
- **Bug**: Writes to `ammount` (double-m). The actual column is `amount` (per migration `2021_03_15_144918:24`). Eloquent silently swallows unknown-attribute writes because the model's `$fillable` doesn't include the typo'd name, so the field is dropped.
- **Symptom in prod**: cheque payments recorded via this path have `amount=NULL`.
- **Fix**: rename → `amount`. Also: this method has no route binding (see §3.1), so the bug never surfaces — but it's a footgun if a router brings the action back.

### 1.2 `payment_status` schema/usage divergence

Three writers, two incompatible interpretations of the same column.

| Writer | Treats `payment_status` as | Lines |
|---|---|---|
| Migration `2021_03_15_144918:18` | `boolean` (true/false) | DB column |
| `Purchase::PAYMENT_UNPAID=0, PARTIAL=1, PAID=2` | integer enum | model constants |
| `PurchaseController::savePaymentDetails()` | boolean | `.php:599, 609` |
| `PurchaseController::recordPayment()` | integer | via `updatePaymentStatus()` |
| Test assertions | mixed | `tests/Feature/.../PurchasePaymentTest.php:59` |

**Result**: a query like `where payment_status = 1` returns rows that were written as `true` AND rows that were written as int `1`, but **misses partial-paid rows** if any path wrote `2`. Reports lie. Audit-trail reconciliation off.

**Fix**:
1. Migration `2026_06_XX_alter_purchases_payment_status_to_tinyint.php`: `ALTER TABLE purchases MODIFY payment_status TINYINT UNSIGNED NULL DEFAULT 0;` plus a data backfill: `UPDATE purchases SET payment_status = IF(payment_status, 2, 0)` before the type change.
2. Remove the legacy boolean writes in `savePaymentDetails()` (and delete the method per §3.1).
3. Drop dynamic-cast fallback in dashboard `PurchaseModel.paymentStatus` (currently `dynamic`); type as `num`.

### 1.3 `receiveNow` has no transaction and no row lock

- **File**: `app/Http/Controllers/Admin/InventoryManagement/PurchaseController.php:513-556`
- **Bug**: Loops over PO lines and increments `ItemStore.stock` without `DB::transaction()` wrapper and without `lockForUpdate()` on `ItemStore`. Two concurrent partial-receives on the same item drift the stock count.
- **Reference good implementation**: `app/Http/Controllers/API/v1/openclaw/PurchaseController.php:307` already does this correctly.
- **Fix**: mirror the OpenClaw pattern. Wrap the whole block in `DB::transaction`, fetch each `ItemStore` with `->lockForUpdate()`, write back inside the same transaction.

### 1.4 Split-transaction in `recordPayment`

- **File**: `app/Http/Controllers/Admin/InventoryManagement/PurchaseController.php:841-879`
- **Original claim**: `BankTransaction::create()` + `PurchasePayment::create()` were inside `DB::transaction`, but the subsequent `updatePaymentStatus()` call at line 879 called `$purchase->save()` outside the closure.
- **Verified on re-inspection (2026-06-10)**: line numbers in the audit were stale. The current code at line 852 calls `$purchase->updatePaymentStatus()` **inside** the closure (between `$purchase->save()` on line 851 and the closure's `return` on line 854). The only thing outside is `$purchase->refresh()` (line 860) which is a read, not a write. **No fix required.** Existing `PurchasePaymentTest` covers the happy path; the existing transaction wrapping is correct.
- **Status**: closed — no-op.

### 1.5 Approval status invariant mismatch

- **Files**: `app/Models/InventoryManagement/Purchase.php` (constants `APPROVAL_*` are `int`) vs `app/Models/InventoryManagement/PurchaseApproval.php` (status column is `enum('pending','approved','rejected')` — strings).
- **Bug**: nothing enforces that `purchase.approval_status = 2` (Approved) implies `latest_approval.status = 'approved'`. A row CAN be flipped to APPROVED via SQL without a matching approval row, or vice versa.
- **Fix options** (pick one):
  - **A**: drop `Purchase.approval_status` column; derive it from `latest()->approval->status` via a computed accessor. One column of truth.
  - **B**: keep both, add a model observer on `PurchaseApproval::saved` that bumps the parent's `approval_status` to match. Document the invariant.
- **Preferred**: (A) — fewer surfaces to keep in sync.

### 1.6 Destroy doesn't cascade; no audit

- **File**: `PurchaseController.php:386-388`
- **Bug**: setting `status=3` is the soft-delete marker, but `PurchaseLine`, `PurchaseAdd`, and `PurchasePayment` have no `onDelete('cascade')` FK constraint. Worse, no `AuditLog::record(...)` row is written.
- **Fix**: (1) wrap destroy in transaction; (2) cascade-soft-delete the child rows; (3) audit row with the void reason + who voided.

---

## 2. Tier 2 — surface inconsistencies (fix as a single grouped PR after Tier 1)

| ID | What differs | Decision |
|---|---|---|
| C1 | Payment record success code: admin=200, openclaw+mobile=201 | Standardize on **201 Created** with `{success: true, data: {...}}`. Update admin to match. |
| C3 | Self-approval: admin + mobile block, openclaw skips intentionally | Document the intentional split in the OpenClaw controller's class docblock. No code change. |
| C5 | Receive response body differs | Standardize on `{purchase_order, lines, received_this_call}` (the OpenClaw shape). Update mobile. |
| C6 | Not-yet-approved receive: 409 (openclaw) vs 403 (mobile) | Standardize on **409 Conflict** — the resource exists but is in the wrong state. Update mobile. |

---

## 3. Tier 3 — dead code / unread columns

### 3.1 `savePaymentDetails()` is uncallable

- **File**: `PurchaseController.php:580-620`
- **Evidence**: `grep -n savePaymentDetails routes/admin.php` returns nothing.
- **Fix**: delete the method. Adding new code on top of it (the `ammount` typo lives here) is a fire risk.

### 3.2 `unit_qty` / `unit_name` written but never read

- **Migration**: `2025_10_13_160816_add_unit_qty_unit_name_to_purchase_lines.php`
- **Written by**: mobile create/update, admin create/update
- **Not read by**: API responses, view templates, OpenClaw receive (which recomputes from `ItemUnit` relation)
- **Fix decision**: are these the source of truth for the line's unit at order time (snapshot — desirable for audit), or are they derived (redundant — drop)?
  - **Recommended**: keep as snapshot, **add to all API responses**, refactor OpenClaw receive to use the snapshot instead of re-deriving.

### 3.3 `PurchaseAdd.amount` not summed on edit

- **File**: `PurchaseController.php:362, 370`
- **Original claim**: on edit, `total` is reset to 0, lines are summed back in, but `PurchaseAdd` amounts aren't re-summed.
- **Verified on re-inspection (2026-06-10)**: line 362 in the current `update()` does `$total += $request->addAmount[$i];` inside its addAmount loop, then writes the final `$total` on line 370. PurchaseAdd amounts ARE included. The audit was wrong.
- **Status**: closed — no-op. A characterization test now locks in the behavior as a regression guard (`PurchaseSafetyNetTest::test_edit_preserves_purchase_add_amounts_in_total`).

---

## 4. Tier 4 — zero audit trail

Purchase is the only mutation surface in the app with **no `AuditLog::record(...)` calls anywhere**. Every other domain model (EcommerceOrder, Customer, SmsTemplate, Sale-cancel) writes audit rows on every state change.

Events that must be audited:
- `purchase_approved` — actor, from/to status, approval comment
- `purchase_rejected` — actor, rejection comment
- `purchase_payment_recorded` — actor, amount, method, bank
- `purchase_received` — actor, lines summary (line_id → qty received this call)
- `purchase_voided` — actor, reason, items returned to stock

Reuse the existing `AuditLog::record()` helper at `app/Models/Reports/AuditLog.php` — same shape every other domain uses.

---

## 5. Tier 5 — test coverage gaps

| Action | Test? | What needs covering |
|---|---|---|
| Admin store/update PO | ❌ | Happy path + adds + line creation |
| Admin destroy | ❌ | Cascade + audit |
| Admin receiveNow | ❌ | Partial receive, full receive, race (two concurrent calls), wrong qty, not approved |
| Admin approve/reject | ❌ | Role gate, self-approval block, approval row created |
| Admin recordPayment | ✓ thin | Add: bank balance updates atomically with status; cheque flow; mobile/openclaw parity |
| OpenClaw approve/reject/receive/pay | ✓ thorough | Already strong (35 tests) — use as the reference shape |
| Mobile receive | ❌ | Mirror OpenClaw tests |

---

## 6. Dashboard parity gaps (apex_dashboard)

Apex_dashboard's `PurchaseModel` already mirrors most backend fields. The two real gaps:

1. **`PurchaseAdd` is not modelled or rendered.** Backend can attach extras (delivery fees, port charges, broker fees). Dashboard shows a total that may be lower than the supplier's actual invoice.
   - **Fix**: add `PurchaseAddModel` to `lib/models/`; expose `adds` in the show-detail response; render them above the Totals card.
2. **`expected` field type drift.** Backend migration `2024_09_12_181439` changed `expected` from `date` to `decimal(10,2)`. Dashboard still types it as `String?` and renders as a date.
   - **Fix**: change type to `num?`; reformat the rendering as currency, not a date. Verify the create/edit form sends a number, not a date string.

A third item, conditional on §1.2 landing first: drop `dynamic` typing on `paymentStatus` / `paymentType` / `amount` / `dateIssued` in the dashboard model; type them strictly.

---

## 7. Fix sequencing (this is the order of work)

Sequence enforced by dependencies. Each phase is a single PR; merge before starting the next.

**Phase 0** — this document committed (no code).

**Phase 1 — safety net (no fixes, all tests)**:
- Characterization tests that **lock in current passing behavior** for every action that's about to be touched (admin CRUD, receiveNow, approve/reject, recordPayment, void).
- Failing tests that **demonstrate each Tier 1 bug** (typo, payment_status type, receiveNow race, split transaction, approval invariant, destroy cascade). These start red; they go green during Phase 2.

**Phase 2 — Tier 1 fixes** (data integrity / concurrency):
- §1.1 — typo / delete `savePaymentDetails()` (combine with §3.1)
- §1.2 — payment_status migration + backfill
- §1.3 — receiveNow transaction + locks
- §1.4 — move updatePaymentStatus inside the transaction
- §1.5 — pick option A or B for approval invariant
- §1.6 — destroy cascade + audit

After Phase 2: all Phase 1 tests should be green. If any are still red, fix the test or fix the implementation — don't ship with red tests "documented as known."

**Phase 3 — Tier 4 audit-log writes** (cheap, mechanical):
- Add `AuditLog::record(...)` for the five events in §4.

**Phase 4 — Tier 3 cleanups**:
- §3.1 — delete `savePaymentDetails` already removed in Phase 2; this phase is a sweep for stragglers.
- §3.2 — surface `unit_qty` / `unit_name` in responses.
- §3.3 — fix `total` recomputation on edit.

**Phase 5 — Tier 2 surface harmonization**:
- §C1, §C5, §C6 — pick canonical shapes, update admin + mobile to match.
- §C3 — document the OpenClaw exception with a clear docblock.

**Phase 6 — Dashboard parity** (apex_dashboard repo):
- §6.1 — `PurchaseAdd` model + render.
- §6.2 — `expected` type fix.
- §6.3 — strict typing once §1.2 lands.

**Then — and only then** — the EWT spec (`development/specs/ewt_purchase_order_spec.md`) becomes safe to implement. Adding tax math on top of payment_status corruption or stock-race bugs would compound the damage.

---

## 8. Acceptance criteria

Phase 2 done when:
- ✅ All Phase 1 tests pass (both characterization and bug-demonstrating).
- ✅ Manual smoke: two concurrent partial receives on the same item land at the correct stock count (run with `--processes=2` or two browser tabs).
- ✅ `grep -rn 'ammount'` returns zero hits in `app/`.
- ✅ `grep -rn 'payment_status.*=.*true\|payment_status.*=.*false' app/` returns zero hits — everyone uses constants.
- ✅ `php artisan tinker --execute='echo Purchase::APPROVAL_APPROVED;'` returns `2`, and all approved POs in DB have a matching PurchaseApproval row with `status='approved'`.

Phase 3 done when:
- ✅ `AuditLog::where('auditable_type', Purchase::class)->count()` is non-zero after a fresh approve/reject/pay/receive/void sequence.

Phase 6 done when:
- ✅ `PurchaseAdd` rendered on dashboard detail page.
- ✅ Dashboard's `expected` parses as a number, renders as currency.
- ✅ `dart analyze` is clean on `purchase_model.dart` after `dynamic` removals.

---

## 9. References

- Audit source: parallel Explore runs 2026-06-09 against `apex_backend@bd971ed` and `apex_dashboard@HEAD`.
- Existing test patterns to mirror:
  - Web admin: `tests/Feature/Admin/Inventory/PurchasePaymentTest.php`
  - OpenClaw: `tests/Feature/API/v1/openclaw/OpenclawPurchasesTest.php`, `OpenclawPurchaseReceiveTest.php`
- Related upcoming spec: `development/specs/ewt_purchase_order_spec.md` (must wait for this remediation to complete).
- Audit-log helper: `app/Models/Reports/AuditLog.php::record()` — reuse as-is.
