# Cashless Payments + Ecommerce Lifecycle — Summary

**Branch:** merged into `dev`
**Period:** 2026-06-06 → 2026-06-07
**Goal:** let admin record received cashless payments against ecommerce orders, give the order a full status lifecycle with proof photos and a status timeline, expose it all to the bot and mobile dashboard, and document the apex_pos / apex_dashboard split.

---

## 1. Order status lifecycle (new)

Status enum on `ecommerce_orders.status`:

| # | Label | Owner | Set by |
|---|---|---|---|
| 0 | Pending | customer | `/shop` checkout |
| 1 | Verified | admin | `/admin/ecommerce-orders/{id}/verify` |
| 2 | Cancelled | admin | `/admin/ecommerce-orders/{id}/cancel` |
| 3 | Paid | system | `SaleCreationService` when a Sale is created with `ecommerce_order_id` |
| 4 | Preparing | admin / dashboard | `mark-preparing` endpoint |
| 5 | Picked Up | admin / dashboard / POS auto | `mark-picked-up` endpoint OR POS sale (auto-telescopes through Paid → Picked Up because the customer is at the counter) |

**Two flows differ on purpose:**
- **POS sale** (`sale.pos_id IS NOT NULL`) → order telescopes verified → paid → picked_up in one shot. Customer is at the counter, paying and collecting in one motion.
- **Cashless web/dashboard sale** (`sale.pos_id IS NULL`) → stops at paid. Customer hasn't arrived. Back office advances preparing/picked_up manually.

Badge variants (matching admin + customer + dashboard):
- Pending → `warning` (amber)
- Verified → `primary` (royal blue)
- Paid → `info` (sky cyan)
- Preparing → `preparing` (violet — custom variant, define `.badge-light-preparing` where Metronic classes are used)
- Picked Up → `success` (emerald)
- Cancelled → `danger` (red)

---

## 2. Payment recording (the headline feature)

### Schema

- `sales.pos_id` → nullable (NULL = recorded via web admin, not a POS terminal)
- `sales.cheque_status` → nullable enum (`pending` / `cleared` / `bounced`)
- `audit_logs.event` widened from ENUM(8 values) → VARCHAR(64)
- New table `sale_payment_proofs` (image attachments per sale)
- New table `ecommerce_order_pickup_proofs` (image attachments per order)
- New table `ecommerce_order_status_changes` (transition log per order)
- `ecommerce_orders.payment_intent` (nullable string — customer's checkout signal)

### Pipeline

`App\Services\SaleCreationService` is the **single source of truth** for sale creation, shared by:
- POS `POST /api/v1/sales`
- Admin web `POST /admin/ecommerce-orders/{id}/record-payment`
- Mobile dashboard `POST /api/v1/mobile/ecommerce-orders/{id}/record-payment`
- OpenClaw bot `POST /api/v1/openclaw/ecommerce-orders/{id}/record-payment`

Whatever rings the sale, the same audit_logs / status_changes / bank deposit / points / inventory hooks fire. Refactored out from `SaleController::store` early in the branch so all four callers share the code.

### Payment types

| Int | Label | Bank fields required? | Auto bank deposit? |
|-----|-------|----------------------|--------------------|
| 1 | Cash | no | no |
| 2 | GCash / E-Wallet | yes | yes |
| 3 | Credit | no | no (credit ledger entry) |
| 4 | Bank Transfer | yes | yes |
| 5 | Cheque | yes | NO — pending until cleared |

Cheques start `cheque_status = pending`. They don't impact bank balance until cleared via the Pending Cheques surface (`/admin/pending-cheques`):
- **Mark Cleared** → creates a BankTransaction with the admin's clearing date + reference, balance goes up
- **Mark Bounced** → writes a `customer_credit_transactions.charge` row, no bank impact, customer still owes

### Admin web UI

- New "Record Payment" button + modal on `/admin/ecommerce-orders/{id}` (Verified or pending non-cancelled orders, gated by `record-cashless-payment` ability which checks `role.sls`)
- 4 payment-method radio cards (Cash / GCash / Bank Transfer / Cheque)
- Select2-AJAX bank + store pickers (live — Banks/Stores added in Settings appear without a refresh)
- File input `proofs[]` (0–5 images, ≤5MB each, JPG/PNG/WEBP/HEIC) with client-side preview thumbnails
- Pre-selects the payment method matching the customer's checkout intent; copy explicitly notes admin can override
- "Paid" panel after recording, with cheque-status callout if applicable + FsLightbox gallery of proofs

### Mobile + bot

Both `/api/v1/mobile/ecommerce-orders` and `/api/v1/openclaw/ecommerce-orders` got matching endpoints:

| Method | Path | Notes |
|---|---|---|
| GET | `/` | paginated list with status / reference / customer / date filters |
| GET | `/pending` | quick "what needs my OK" feed for the badge / bot prompt |
| GET | `/{id}` | full detail including status_history, proofs, sale summary |
| POST | `/{id}/verify` | — |
| POST | `/{id}/cancel` | accepts `reason` |
| POST | `/{id}/record-payment` | multipart: payment_method + store + bank + proofs[] |
| POST | `/{id}/mark-preparing` | — |
| POST | `/{id}/mark-picked-up` | mobile multipart: proofs[] |

OpenClaw uses ability-scoped tokens (`openclaw:ecommerce-orders:verify`, `...:cancel`, `...:record-payment`, `...:mark-preparing`, `...:mark-picked-up`). Mobile uses Passport.

---

## 3. Status history + audit

### `ecommerce_order_status_changes` table

One row per transition with `from_status`, `to_status`, `changed_by` (null for customer-initiated), `note`, `created_at`. Logged at every transition point in code (cart checkout, verify, cancel, mark-preparing, mark-picked-up, and the auto-advance to PAID in `SaleCreationService`).

Backfill migration synthesises history for pre-existing orders from `created_at`, `verified_at`, `cancelled_at`, and `sale.created_at`. Idempotent — re-running skips orders with existing history.

### Admin timeline UI

Card on `/admin/ecommerce-orders/{id}` show page renders the full timeline — coloured dots per status variant, from → to transition, actor name (or "customer"), absolute + relative timestamp, optional note.

### Customer timeline UI

`/customer/orders/{ref}` shows the same data with the actor + notes hidden (the internal labels like "Cashless payment recorded" aren't customer-facing).

### Audit logs (narrow override)

Memory rule says "don't audit Sale" because `pos_logs` is authoritative. The cashless flow is a narrow exception — admin-recorded sales (pos_id IS NULL) write audit_logs rows on **both** `EcommerceOrder` (event: `payment_recorded`) and `Sale` (event: `created_via_admin`). POS-originated sales still go through pos_logs only. Cheque clearing/bouncing also audit-logs.

---

## 4. Proof photos

Two distinct kinds, separate tables, same upload pipeline (`App\Services\ReceiptStorage`):

| Attached to | Captured when | Surfaced on |
|---|---|---|
| `sale_payment_proofs` (Sale FK) | recording payment | admin order show, customer order detail |
| `ecommerce_order_pickup_proofs` (Order FK) | marking picked up | admin order show "Picked Up" panel, customer order detail "Pickup Confirmation" |

Both render in FsLightbox galleries. Validation runs BEFORE the status transition so a bad file leaves the order at the previous status (no half-advance).

---

## 5. Customer-facing additions

- **My Orders** (`/customer/orders`)
  - Filters: search (reference + note, live), status pills, sort (Newest / Oldest / Highest total / Lowest total)
  - Live result count + Clear filters
  - Redesigned cards: status accent stripe down the left edge, status chip, compact item summary, prominent total, animated "View details →" CTA, hover lift
  - URL-backed filter state via Livewire `#[Url]` — shareable, refresh-safe
  - Distinct empty states ("no orders yet" with Browse Products CTA vs "no matches" with Clear filters CTA)

- **Order detail** (`/customer/orders/{reference}`)
  - **Reference, not ID, in the URL** — `{ecommerceOrder:reference}` route binding means customers can't enumerate by incrementing the ID
  - 5-stop visual stepper + status timeline
  - Payment card with method, bank, reference, recorded date, cheque status callouts (pending/cleared/bounced with appropriate copy), proof photo gallery
  - Pickup Confirmation card with proof photos when picked_up
  - **QR code** encoding `/admin/ecommerce-orders/lookup/{reference}` so cashier scans the customer's phone and the admin show page opens directly (no ID leakage)
  - "Paying: <method>" badge while unpaid, sourced from the customer's checkout choice
  - "Awaiting payment" placeholder when no sale exists yet

- **Cart checkout** (`/shop/cart`)
  - 2×2 radio grid: "How will you pay?" (Cash on Pickup / GCash / Bank Transfer / Cheque)
  - Stores into `ecommerce_orders.payment_intent`. Customer not actually paying — it's a hint for admin + cashier
  - Caption clarifies "you're not paying now — this just helps the store know what to expect"

---

## 6. Admin-only additions

- **New orders bell** — beside Access Requests in the navbar, visible to users with `role.sls`. Polls `/admin/ecommerce-orders/pending-feed` every 10s, click-through to each order
- **Pending Cheques** at `/admin/pending-cheques` — Yajra DataTable with Mark Cleared / Mark Bounced actions, sidebar entry under Banking
- **Daily Summary** at `/admin/reports/daily-summary` — date picker, KPI cards (sales, profit, refunds, transactions), per-method cashless breakdown, pending cheques aging panel
- **Daily email** (existing `report:generate --type=daily` cron) — extended with cashless-breakdown table + pending cheques alert + "Review pending cheques" deep-link
- **Audit Log** (existing) — now captures all the new named events: `payment_recorded`, `created_via_admin`, `cheque_cleared`, `cheque_bounced`, `marked_preparing`, `marked_picked_up`

---

## 7. Bug fixes shipped along the way

- **Select2 misclick** on Record Payment modal — dropdownParent moved to `.modal-body`, focus pushed to search box on `select2:open` (Bootstrap 5 was stealing first interaction)
- **Dark mode** getting wiped by visiting `/shop` — admin's persisted theme moved off Metronic's default `localStorage["data-bs-theme"]` key to `apex-admin-bs-theme`. Mirror via MutationObserver since Metronic's `KTEventHandler` is custom (not DOM events)
- **`@php(inline)` Blade directives** mis-compiling — replaced with `@php / @endphp` blocks on the order show page (was silently breaking every `@elseif` after the bad directive, hiding the Record Payment button)
- **Receipt screen view** crashing on cashless sales (`$sale->pos->min` on null) — guarded by `@if($sale->pos)` and shows "Recorded via Web Admin" instead
- **Customer login → /admin/login** redirect bug — new `safeIntended()` helper only honours intended URLs starting with `/customer` or `/shop`, ignores admin intents that leaked across guards
- **Already-paid orders stuck at "Verified"** — backfill migration walks orders with a linked Sale and bumps them to PAID
- **`sales_report_exclusion` and `voucher` test failures** unchanged, listed in the existing pre-existing-failures memory
- **Native `confirm()` dialogs** replaced with SweetAlert on Verify / Cancel / Mark Preparing / Mark Picked Up

---

## 8. New tables (count: 4)

```
sale_payment_proofs           (sale_id, path, uploaded_by, note, timestamps)
ecommerce_order_pickup_proofs (ecommerce_order_id, path, uploaded_by, note, timestamps)
ecommerce_order_status_changes (ecommerce_order_id, from_status, to_status,
                                changed_by, note, created_at)
                                + backfill from pre-existing timestamps
```

## Column additions

```
sales.pos_id           NOT NULL  →  nullable
sales.cheque_status    new (string)
audit_logs.event       ENUM(8)   →  VARCHAR(64)
ecommerce_orders.payment_intent  new (string)
```

---

## 9. Specs written / updated

| Spec | Status |
|---|---|
| `development/specs/cashless_payment_recording/plan.md` | New — original feature spec with 6 locked decisions |
| `development/specs/apex_pos_ecommerce_orders_spec.md` | Updated — extended lifecycle, what POS does NOT do, cashier UX table, payment_intent rendering, POS-telescopes-to-picked-up note |
| `development/specs/apex_dashboard_ecommerce_lifecycle_spec.md` | New — full Flutter team brief: model extensions, service methods, UI plan, FCM handler, POS-vs-Dashboard boundary table |

The two Flutter-app specs reference each other and agree on the split.

---

## 10. Test coverage added

Roughly 60 new feature tests across:
- `tests/Feature/Admin/Ecommerce/EcommerceOrderRecordPaymentTest.php` — 23 cases (payment + status advancement + proof uploads + lifecycle)
- `tests/Feature/Admin/Accounting/PendingChequeFlowTest.php` — 6 cases
- `tests/Feature/API/v1/openclaw/OpenclawEcommerceOrdersTest.php` — 13 cases
- `tests/Feature/API/v1/mobile/MobileEcommerceOrdersTest.php` — 8 cases
- `tests/Feature/Customer/EcommerceOrderShowTest.php` — 5 cases (auth + reference URL + lookup)
- `tests/Feature/Customer/CustomerOrdersFilterTest.php` — 6 cases (search + filter + sort)
- `tests/Feature/Customer/CustomerLoginIntendedTest.php` — 5 cases (intended-URL regression)
- Plus the POS sale → PICKED_UP test in `SaleControllerTest.php`

All passing on dev. Pre-existing failures unrelated to this work documented in memory.

---

## 11. New + extended npm-style dependencies

- `endroid/qr-code` ^6.1 — added for the QR on the customer order detail page

No other new composer packages. No new npm packages.

---

## 12. Memory updates

- `feedback_audit_scope.md` — narrowed the no-audit-on-Sale rule to POS-originated sales only; documented the admin-recorded-sale exception

---

## 13. Out of scope (explicit)

- **Z-Reading integration** — admin sales correctly stay out of Z-readings (Z-readings filter by `pos_id = $reading->pos_id` and our cashless sales are NULL). No Z-reading code touched.
- **BIR Sales Invoice / submission** — owner is researching separately. No spec, no code.
- **Refunds of admin-recorded sales** — refund button hidden when `pos_id IS NULL`; refund flow stays POS-only for now.
- **Multi-tenancy strict enforcement** — banks are not yet tenant-scoped (banks table has no `user_id`). Tenancy guard in `RecordOrderPaymentService` checks store + admin against the order's customer; loosened to treat `customer.user_id = 0` (shop-registered placeholder) as in-scope until the deferred multi-tenancy work tightens it.

---

## 14. Files touched (rough breakdown)

- 9 controllers (admin + mobile + openclaw + customer)
- 6 services (`SaleCreationService`, `RecordOrderPaymentService`, `MarkChequeClearedService`, `MarkChequeBouncedService`, `ReportService`, `SaleCreationData` DTO)
- 4 models added, 2 modified
- 8 migrations
- ~12 Blade views (admin show, customer detail, customer my-orders, cart, navbar partial, daily summary, pending cheques, etc.)
- 8 feature test files

Roughly 80 commits between `60176a1 Spec the cashless payment recording feature` and the latest fix `4703b69 Fix customer login redirecting to /admin/login`.

---

## 15. What's next (suggestions, not decisions)

- **POS pickup confirmation** — apex_pos could add a one-tap "Confirm Pickup" so the cashier marks pickup instead of back office. Spec already calls this out as future scope.
- **Daily Summary push to chat** — if you want the daily summary to land in Slack/Telegram instead of just email, the data is already structured for it.
- **Banks tenant-scoped** — when the deferred multi-tenancy work lands, the loosened guard on banks gets tightened automatically.
- **Refund flow for admin-recorded sales** — gap left open intentionally; design when you see real refund traffic.
