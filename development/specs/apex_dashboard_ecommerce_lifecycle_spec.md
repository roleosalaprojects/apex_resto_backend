# apex_dashboard — Ecommerce Order Lifecycle

**Status:** Draft for review · 2026-06-07
**Target app:** `apex_dashboard` (Flutter mobile/tablet back-office)
**Backend version:** `feature/cashless-payment-recording` branch
**Companion specs:** `apex_pos_ecommerce_orders_spec.md`

---

## 1. Goal

The apex_dashboard already lists ecommerce orders and supports verify / cancel for pending ones. This spec extends the dashboard to drive the **full lifecycle** the backend now exposes.

**Important:** apex_dashboard and apex_pos are separate apps with separate flows. Don't conflate them. The boundary is in §1a below — read it before scoping any UI work.

## 1a. POS vs Dashboard — who does what

The dashboard is **back-office mode**: remote ops by the owner / manager. The POS is **at the counter**: cashier rings up sales when the customer arrives. They never duplicate each other on the same action.

| Action | apex_pos | apex_dashboard | Why |
|---|---|---|---|
| List verified orders | ✓ ("ready to ring up") | ✓ (full list with filters) | POS only needs the ring-up queue; dashboard needs the broader view |
| View order detail | ✓ (line items for scanning) | ✓ (full lifecycle + history + proofs) | Different UX needs |
| Verify a pending order | ✗ | ✓ | Back-office approval |
| Cancel a pending order | ✗ | ✓ | Back-office approval |
| Process payment at counter | ✓ — via `POST /api/v1/sales` with `ecommerce_order_id` | ✗ | The customer is at the till; POS sale flow handles it. Server-side auto-advances order all the way to **PICKED_UP** (paid + collected in one action). |
| Record cashless payment remotely | ✗ | ✓ — via `POST /api/v1/mobile/ecommerce-orders/{id}/record-payment` | Owner received GCash/bank/cheque payment without being at the store |
| Mark preparing | ✗ | ✓ | Back-office coordination — packing crew has started |
| Mark picked up | ✗ (future scope only) | ✓ | Back-office signs off; future POS spec could add a "Confirm Pickup" button so the cashier handles it directly |
| Clear pending cheques | ✗ | ✗ | Web only — finance review at `/admin/pending-cheques` |
| View Daily Summary report | ✗ | ✗ | Web only at `/admin/reports/daily-summary` |

**Key takeaway:** the POS and the dashboard ring different bells:

- **POS path** (cashier rings up a verified order): server telescopes the order all the way to **PICKED_UP** in one shot. The customer is at the counter; they pay and walk out with the goods in the same action. The dashboard will see the order jump straight from VERIFIED to PICKED_UP — no intermediate states to advance.

- **Dashboard path** (admin records cashless payment remotely): server advances only to **PAID**. The customer isn't at the store yet; their GCash / bank transfer / cheque has come in but the goods haven't moved. The dashboard owner then advances `paid → preparing → picked_up` manually as the order progresses.

The dashboard's record-payment endpoint is exclusively for cashless payments received away from the till (e.g., a GCash transfer the owner sees in their phone). It will never reach PICKED_UP automatically — that's a manual step in the dashboard.

See `apex_pos_ecommerce_orders_spec.md` for the POS side of the same lifecycle.

---

## 2. Lifecycle

The dashboard now drives the full status flow:

```
pending  →  verified  →  paid  →  preparing  →  picked_up
                ↘ cancelled (parallel terminal)
```

Owner / authorized staff use the mobile dashboard to: record cashless payments against orders (GCash, bank transfer, cheque, cash) with optional proof photos, advance the operational status (preparing → picked up), and capture handover proof photos. Every action mirrors the admin web flow — same audit log, same status history, same auto-advance behavior.

A dashboard notification badge surfaces pending orders the moment they're placed so the owner doesn't have to refresh.

---

## 3. Backend API (already shipped)

All routes prefixed `/api/v1/mobile`, authenticated via Laravel Passport (`auth:api`). The authenticated user is the actor on every transition. Tenant scoping is enforced server-side via `customers.user_id` matching `auth()->user()->user_id`.

### Reads

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/ecommerce-orders` | Paginated list. Query params: `status` (0–5), `reference` (substring), `customer_id`, `date_from`, `date_to`, `per_page` (1–100, default 20) |
| `GET` | `/ecommerce-orders/pending` | Returns `{ count, orders }` — up to 50 pending. Use this to drive the badge / pull-to-refresh feed. |
| `GET` | `/ecommerce-orders/{id}` | Full detail: order, lines + item, sale + bank + proofs, pickupProofs, status_history (with `from_label`/`to_label`/`to_badge_variant`/`changed_by`/`at`), `status_label`, `status_badge_variant`. |

### Writes

| Method | Path | Body | Notes |
|---|---|---|---|
| `POST` | `/ecommerce-orders/{id}/verify` | — | 422 if not pending. |
| `POST` | `/ecommerce-orders/{id}/cancel` | `{ reason? }` | **Mobile API currently 422s when status ≠ pending.** The admin web UI (June 2026) gained a cancel-paid-order / refund-picked-up flow via `CancelEcommerceOrderService` — refund Sale + stock return + bank rollback — but mobile parity is a known follow-up (see §11). For now dashboard should grey out the Cancel button on any non-pending order and direct admins to the web for refunds. |
| `POST` | `/ecommerce-orders/{id}/record-payment` | multipart: `payment_method`, `store_id`, `bank_id?`, `bank_amount?`, `reference_number?`, `note?`, `proofs[]?` | See §3 for shape. Auto-advances to PAID. |
| `POST` | `/ecommerce-orders/{id}/mark-preparing` | — | 422 if not paid. |
| `POST` | `/ecommerce-orders/{id}/mark-picked-up` | multipart: `proofs[]?` | 422 if not preparing. |

### Status enum (mirrors `App\Models\Ecommerce\EcommerceOrder::STATUS_*`)

```
0  pending
1  verified
2  cancelled
3  paid
4  preparing
5  picked_up
```

### Badge variant → colour mapping

```
warning    #f59e0b   (amber)         → Pending
primary    #2563eb   (royal blue)    → Verified
info       #0ea5e9   (sky cyan)      → Paid
preparing  #8b5cf6   (violet)        → Preparing   ← custom variant
success    #10b981   (emerald)       → Picked Up
danger     #ef4444   (red)           → Cancelled
```

Add these to `lib/config/design_tokens.dart` or wherever the existing status colours live.

---

## 4. Payment recording details

`POST /ecommerce-orders/{id}/record-payment` accepts:

| Field | Type | Required | Notes |
|---|---|---|---|
| `payment_method` | string or int | yes | `cash` / `gcash` / `bank_transfer` / `cheque` — or the ints 1/2/4/5. **`credit` is rejected** (POS-only). |
| `store_id` | int | yes | Stock deducts from this store. Use the dashboard's store picker (existing pattern). |
| `bank_id` | int | yes when method ≠ cash | The receiving bank. |
| `bank_amount` | numeric | yes when method ≠ cash | Amount actually received (lets you record partial or rounded). |
| `reference_number` | string ≤120 | yes when method ≠ cash | GCash ref, transfer ref, or cheque number. |
| `note` | string ≤500 | no | Free text, surfaces on the audit row. |
| `proofs[]` | image file (×0–5) | no | JPG/PNG/WEBP/HEIC, ≤5MB each. Persisted as SalePaymentProof. |

**Cheque payments** (`payment_method=cheque`) auto-set `sales.cheque_status = pending`. The bank balance does NOT move until admin clears the cheque from `/admin/pending-cheques` — that surface stays web-only for now. Dashboard surfaces the pending status visually but doesn't expose clearing.

**Response on success:**
```json
{
  "success": true,
  "message": "Payment recorded as Sale #WEB-ECO-XXXX.",
  "data": {
    "order": { ...full order... },
    "sale":  { ...sale with paymentProofs, bank... }
  }
}
```

---

## 5. Dashboard changes

### 4.1 Model: extend `EcommerceOrderModel`

`lib/models/ecommerce_order_model.dart` currently has `id, reference, customer_id, total, qty, status, note, verifiedAt, createdAt, customer, lines, sale`. Add:

```dart
// new fields
String? cancelledAt;
String? statusLabel;            // from /show — "Pending" / "Verified" / etc.
String? statusBadgeVariant;     // "warning" / "primary" / etc.
String? paymentIntent;          // 'cash_on_pickup' / 'gcash' / 'bank_transfer' / 'cheque' / null
String? paymentIntentLabel;     // "Cash on Pickup" / "GCash / E-Wallet" / ...
int?    intendedSalePaymentType; // 1 / 2 / 4 / 5 — pre-select the matching radio
                                 // in the Record Payment sheet
List<EcommerceOrderStatusChange>? statusHistory;
List<EcommerceOrderPickupProof>? pickupProofs;
```

`payment_intent` is what the customer picked at /shop checkout ("how do you intend to pay?"). It's NOT money received — just a signal that:
- Pre-fills the Record Payment sheet's payment method radio
- Surfaces to the dashboard owner so they know what to expect
- Is independent of the actual sale that may eventually exist


New companion models:

```dart
class EcommerceOrderStatusChange {
  int?   fromStatus;       // nullable for the "order placed" event
  String? fromLabel;
  int    toStatus;
  String toLabel;
  String toBadgeVariant;
  String? changedBy;       // null when customer placed
  String? note;
  DateTime at;
}

class EcommerceOrderPickupProof {
  int    id;
  String url;              // absolute public URL
  DateTime? createdAt;
}
```

Extend `EcommerceOrderSale` to include `chequeStatus`, `paymentType` (int), `bank` ({id, bankName, accountName}), `paymentProofs` (List of `{id, url}`), and **`refundSales`** (List of `{id, son, total, createdAt}` — only populated when the sale has been refunded).

A refund Sale is a separate row written by `App\Services\CancelEcommerceOrderService` when an admin cancels a PAID / PREPARING / PICKED_UP order from the web UI. It has `type=true`, `sale_id` back-pointing at the original, `ecommerce_order_id=NULL`. The dashboard should render a "Refunded — Sale #{son}" badge alongside the paid panel when `sale.refundSales` is non-empty, even though the dashboard can't trigger the refund itself yet (see §11).

### 4.2 Service: extend `ecommerce_order_controller.dart`

New methods (mirror the existing `verifyEcommerceOrder` / `cancelEcommerceOrder` patterns):

```dart
Future<Map<String, dynamic>?> fetchPendingOrders(BuildContext context);
Future<Map<String, dynamic>?> recordOrderPayment(
  BuildContext context,
  int orderId, {
  required String paymentMethod,
  required int storeId,
  int? bankId,
  num? bankAmount,
  String? referenceNumber,
  String? note,
  List<File>? proofs,
});
Future<Map<String, dynamic>?> markOrderPreparing(BuildContext context, int orderId);
Future<Map<String, dynamic>?> markOrderPickedUp(
  BuildContext context,
  int orderId, {
  List<File>? proofs,
});
```

For multipart uploads (`record-payment`, `mark-picked-up` with photos), use the existing `ApiClient` multipart helper if it exists, or follow the `bank-transactions/{id}/proof` pattern (already in the dashboard for bank-transaction proofs).

### 4.3 UI: extend the existing pages

#### `lib/responsive/pages/ecommerce/ecommerce_order_index.dart`

- Add status filter pills at the top (All / Pending / Verified / Paid / Preparing / Picked Up / Cancelled). Drives `?status=` query param.
- Each list tile renders a coloured stripe down the left edge keyed by `statusBadgeVariant` — matches the customer's `/customer/orders` cards on web.
- Pull-to-refresh hits the standard list endpoint.
- Empty states distinguish "no orders" from "no matches" (analogous to web).

#### `lib/responsive/pages/ecommerce/ecommerce_order_show.dart`

Existing page shows lines + verify/cancel buttons. Extend with:

1. **Status header**: large status chip using `statusBadgeVariant`, prominent at top.
2. **Stepper**: visual progression through `pending → verified → paid → preparing → picked_up`, highlighting current step. Cancelled orders show a red alert and skip the stepper (same UX as `/customer/orders/{id}`).
3. **Status History timeline**: vertical list of `statusHistory` entries, most recent first. Each row: status chip, absolute + relative timestamp, who changed it, optional note.
4. **Action buttons** keyed off current status:

   | Status | Buttons |
   |---|---|
   | Pending | Verify · Cancel · **Record Payment** |
   | Verified | **Record Payment** · Cancel |
   | Paid | **Mark Preparing** |
   | Preparing | **Mark Picked Up** |
   | Picked Up / Cancelled | — (read only) |

5. **Payment card** (when `order.sale` exists): method, amount, bank, reference, recorded date, cheque status callout (pending/cleared/bounced). Payment proof thumbnails open in a fullscreen image viewer (use `cached_network_image` + a Hero transition).
6. **Pickup card** (when `pickupProofs` is non-empty): grid of proof thumbnails, same fullscreen viewer.

#### Record Payment sheet (new — `ecommerce_record_payment_sheet.dart`)

Bottom sheet with:
- Payment method radio (Cash / GCash / Bank Transfer / Cheque) — segmented buttons or pills
- Store picker (reuse existing store dropdown)
- Conditional fields when method ≠ Cash: Bank picker, Reference Number, Amount Received (pre-filled with order total)
- Note (optional)
- Photo picker: `image_picker` for camera + gallery, max 5 images, thumbnails with × to remove
- Submit button calls `recordOrderPayment(...)` → on success returns to detail page and refreshes

#### Mark Picked Up sheet (new — `ecommerce_mark_picked_up_sheet.dart`)

Lighter weight than Record Payment:
- Confirmation message: "{customer} is picking up {reference}?"
- Photo picker (same component as above; max 5, optional)
- Submit calls `markOrderPickedUp(orderId, proofs: ...)`

#### Mark Preparing

Simple confirm dialog (no photo upload needed). One POST to `/mark-preparing`, refresh on success.

### 4.4 Push notification handling

Backend already sends FCM with payload `{type: 'ecommerce_order', id: '<order_id>'}` to users with `role.sls = true` when a new order is placed via /shop (`CartPage::checkout`). The dashboard's `push_notification_service.dart` should:

1. Add a handler branch for `type == 'ecommerce_order'`.
2. On tap, deep-link to the order detail page via `Navigator.push(...EcommerceOrderShow(...))`.
3. In foreground, surface as an in-app banner + increment the pending badge.

### 4.5 Pending badge

Add a periodic poll (every 30–60s while the dashboard is foregrounded) to `GET /ecommerce-orders/pending` and surface the `count` as a badge on the ecommerce-orders nav entry. When FCM fires `ecommerce_order` while the app is open, increment the badge optimistically and trigger a refresh.

---

## 6. Out of scope (web-only for now)

- **Cheque clearing** (`/admin/pending-cheques`) — finance review surface, stays web-only.
- **Pending Cheque list view** — same reason.
- **Daily Summary report** — web-only at `/admin/reports/daily-summary`. Future spec can mobile-ize it.
- **Status filter on POS** — the POS already has its own EcommerceOrder feed via `/api/v1/ecommerce-orders` (note the separate prefix); see the apex_pos_ecommerce_orders_spec for changes there.
- **Cancel-paid / Refund-picked-up orders** — see §11. The web admin can do this; the mobile API can't yet.
- **Editing customer SMS opt-in / phone-verified status** — read-only on the dashboard for now. Toggle lives on the customer profile at `/customer/profile`.

---

## 7. Migration / rollout

No database migrations needed — the dashboard is API-driven and the backend schema is already in place.

Suggested order:
1. Model + service layer extensions (no UI yet) → verify with manual API call.
2. Extend `ecommerce_order_show.dart`: status header + stepper + history timeline.
3. Add Record Payment sheet.
4. Add Mark Preparing / Mark Picked Up flows.
5. FCM handler + pending badge.

Each step is independently shippable.

---

## 8. Testing notes

- Mock the seven new endpoints in `test/services/` similarly to the existing `EcommerceOrderControllerTest` patterns.
- Widget tests for the Record Payment sheet's conditional bank fields (assert they appear / disappear when payment method changes).
- Manual smoke test: place an order via `/shop` (web) → verify the dashboard's pending badge increments via FCM → tap → detail page → record cash payment → status flips to Paid → mark preparing → mark picked up with one photo → confirm the photo round-trips and appears in both the dashboard and the customer's `/customer/orders/{ref}` page.

---

## 9. References

- Backend controller: `app/Http/Controllers/API/v1/mobile/EcommerceOrderController.php`
- Backend tests: `tests/Feature/API/v1/mobile/MobileEcommerceOrdersTest.php`
- Routes: `routes/api/mobile.php` (`ecommerce-orders` prefix block)
- Status / proof models in backend:
  - `app/Models/Ecommerce/EcommerceOrder.php` (statusBadgeVariant, statusLabel, statusChanges, pickupProofs)
  - `app/Models/Ecommerce/EcommerceOrderStatusChange.php`
  - `app/Models/Ecommerce/EcommerceOrderPickupProof.php`
  - `app/Models/Pos/SalePaymentProof.php`
  - `app/Models/Pos/Sale.php` — gained `refundSales()` HasMany in June 2026
- Web counterparts (same flow, useful for UX reference):
  - Admin: `resources/views/admin/ecommerce/ecommerce-orders/show.blade.php`
  - Customer: `resources/views/customer/order-show.blade.php`

---

## 10. Customer payload — new fields (June 2026)

The Customer object returned inside `order.customer` gained three fields. Read-only on the dashboard for now; intended for surface-level CRM context.

| Field | Type | Description |
|---|---|---|
| `phone` | string | Now stored in canonical `09XXXXXXXXX` form with a UNIQUE constraint at the DB level. No two customers share a phone. |
| `phone_verified_at` | datetime\|null | Stamped when the customer proved phone ownership via OTP at `/shop` registration. Customers created via POS counter / admin CRUD / imports may have this null. Surface as a "verified" badge when set. |
| `sms_notifications_enabled` | boolean | Customer's opt-in for transactional SMS — order verified, paid, picked up, cancelled. Default `true`. Surface as informational ("Texts: ON / OFF") on the order detail page; do not allow editing yet. |

Dashboard should render these alongside the existing `name` / `code` / `email` so an admin investigating a fraud report can see the trust-anchor status at a glance.

---

## 11. Known follow-up — cancel/refund parity with web admin

**Status:** mobile API is NOT at parity with the web admin's cancel flow. This is a known gap; the spec describes the current behavior so dashboard UX matches.

The web admin (June 2026) added a unified cancel-or-refund pipeline at `App\Services\CancelEcommerceOrderService` that handles all non-cancelled states:

- **Pending / Verified** → flip status to CANCELLED (no sale exists yet).
- **Paid / Preparing** → write a refund Sale (`type=true`, `sale_id=originalSale.id`, `ecommerce_order_id=NULL`), mirror SaleLines, dispatch `UpdateItemStocksJob` to return stock, dispatch `ProcessEWalletPaymentJob` for bank/e-wallet refunds, then flip status to CANCELLED. Triggers an `order.cancelled` SMS via the observer.
- **Picked Up** → same mechanics, but UX wording calls it "Refund" since the customer must physically bring the goods back before processing.

The mobile API's `POST /ecommerce-orders/{id}/cancel` at `app/Http/Controllers/API/v1/mobile/EcommerceOrderController.php` still has the old `if (! $ecommerceOrder->isPending()) return $this->error('Only pending orders can be cancelled.', 422);` guard.

**For the dashboard implementation today:**

- Cancel button is enabled only when `order.status === 0` (pending).
- When `sale.refundSales` is non-empty, render a red "Refunded — Sale #{son}" panel and disable all action buttons. The refund was performed via the web admin.

**Future mobile-parity work** (separate ticket):

1. Backend: route `POST /api/v1/mobile/ecommerce-orders/{id}/cancel` through `CancelEcommerceOrderService::cancel($order, auth()->id(), $reason)` instead of the inline pending-only guard. Mirror the role check (`sls` flag) already on the web side.
2. Dashboard: enable Cancel button on all non-cancelled states; show a confirmation sheet that describes the action ("X items return to inventory · ₱Y refund will be recorded · Customer gets SMS"); branch the wording — "Cancel" on PAID/PREPARING, "Refund" on PICKED_UP. The web counterpart's modal at `resources/views/admin/ecommerce/ecommerce-orders/show.blade.php` is the reference UX.
3. Audit: every cancellation already writes an `AuditLog` row (`event = 'order_cancelled'`, `auditable_type = EcommerceOrder`, payload includes refund_sale_id, refund_total, payment_type, bank_refund, lines_returned_to_stock). No mobile-side audit work needed once the API routes through the service.

---

## 12. Related backend infrastructure (June 2026)

These don't change the dashboard's API contract, but are worth knowing when something feels off:

- **Order references** are now 12 hex chars (`ECO-XXXXXXXXXXXX`) generated via `bin2hex(random_bytes(6))`. Pre-June 2026 orders still have 8-char refs. UI should not assume a fixed width; the route binding accepts either.
- **SMS notifications** are dispatched by `App\Jobs\Ecommerce\SendOrderUpdateSmsJob` whenever a status change row is written, behind `App\Contracts\SmsRelayContract`. The active provider is config-driven (`SMS_RELAY` env: `verosms` or `sms_gate`). Templates live in `sms_templates` and are admin-editable at `/admin/settings/sms-templates`. No dashboard surface needed — this is transparent to the API consumer.
- **AuditLog events** logged on this lifecycle: `payment_recorded`, `marked_preparing`, `marked_picked_up`, `order_cancelled`, plus the legacy `cheque_bounced` / `cheque_cleared`. If the dashboard ever surfaces an audit timeline, those are the event names.
- **Sale.sale_id** for refund Sales — the `refundSales()` HasMany on `App\Models\Pos\Sale` returns rows where `sale_id = this.id AND type = true`.
- **Order.sale** (HasOne) is now defensively filtered to require `sales.created_at >= ecommerce_orders.created_at` — prevents recycled-id phantoms after a TRUNCATE that bypasses ON DELETE SET NULL. The mobile API consumes this transparently.
