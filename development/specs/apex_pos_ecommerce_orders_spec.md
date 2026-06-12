# Apex POS — Ecommerce Orders API Spec

## Overview

The Apex POS application consumes ecommerce orders placed by customers through the web storefront. Only **verified** orders (status = 1) are exposed to the POS. This spec documents the API endpoints, data structures, and integration flow for the POS app to retrieve and process ecommerce orders.

---

## Authentication

All endpoints require a valid Passport Bearer token.

```http
Authorization: Bearer {access_token}
Content-Type: application/json
Accept: application/json
```

Tokens are obtained via `POST /api/v1/auth/login`.

---

## Endpoints

### 1. List Verified Ecommerce Orders

```
GET /api/v1/ecommerce-orders
```

Returns all ecommerce orders that have been verified by an admin. Orders are sorted by `verified_at` descending (most recently verified first).

**Response (200):**

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "reference": "ECO-A1B2C3D4",
      "customer_id": 5,
      "total": "1250.00",
      "qty": 4,
      "status": 1,
      "note": "Please pack carefully",
      "verified_by": 2,
      "verified_at": "2026-01-28T10:30:00.000000Z",
      "cancelled_by": null,
      "cancelled_at": null,
      "created_at": "2026-01-28T09:15:00.000000Z",
      "updated_at": "2026-01-28T10:30:00.000000Z",
      "deleted_at": null,
      "customer": {
        "id": 5,
        "name": "Juan Dela Cruz",
        "code": "CUST-00005",
        "phone": "09171234567"
      }
    }
  ]
}
```

**Filtering:** Only orders with `status = 1` (verified) are returned. Pending (0) and cancelled (2) orders are excluded.

---

### 2. Show Single Ecommerce Order

```
GET /api/v1/ecommerce-orders/{id}
```

Returns a single order with its customer info and line items (with item details).

**Path Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | integer | The ecommerce order ID |

**Response (200):**

```json
{
  "success": true,
  "data": {
    "id": 1,
    "reference": "ECO-A1B2C3D4",
    "customer_id": 5,
    "total": "1250.00",
    "qty": 4,
    "status": 1,
    "note": "Please pack carefully",
    "verified_by": 2,
    "verified_at": "2026-01-28T10:30:00.000000Z",
    "cancelled_by": null,
    "cancelled_at": null,
    "created_at": "2026-01-28T09:15:00.000000Z",
    "updated_at": "2026-01-28T10:30:00.000000Z",
    "deleted_at": null,
    "customer": {
      "id": 5,
      "name": "Juan Dela Cruz",
      "code": "CUST-00005",
      "phone": "09171234567"
    },
    "lines": [
      {
        "id": 1,
        "ecommerce_order_id": 1,
        "item_id": 42,
        "item_name": "Premium Rice 5kg",
        "qty": 2,
        "price": "350.00",
        "sub_total": "700.00",
        "created_at": "2026-01-28T09:15:00.000000Z",
        "updated_at": "2026-01-28T09:15:00.000000Z",
        "item": {
          "id": 42,
          "barcode": "8801234567890",
          "name": "Premium Rice 5kg"
        }
      },
      {
        "id": 2,
        "ecommerce_order_id": 1,
        "item_id": 15,
        "item_name": "Cooking Oil 1L",
        "qty": 2,
        "price": "275.00",
        "sub_total": "550.00",
        "created_at": "2026-01-28T09:15:00.000000Z",
        "updated_at": "2026-01-28T09:15:00.000000Z",
        "item": {
          "id": 15,
          "barcode": "4801234567890",
          "name": "Cooking Oil 1L"
        }
      }
    ]
  }
}
```

**Error (404):**

```json
{
  "success": false,
  "message": "Resource not found"
}
```

---

## Data Structures

### EcommerceOrder

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Primary key |
| `reference` | string | Unique human-readable reference. Format changed June 2026: orders created before then are `ECO-XXXXXXXX` (8 hex); new orders are `ECO-XXXXXXXXXXXX` (12 hex via `bin2hex(random_bytes(6))`). The route binding accepts any length — POS UI should not assume a fixed width. |
| `customer_id` | integer | FK to `customers` table |
| `total` | decimal(12,2) | Sum of all line sub_totals |
| `qty` | integer | Sum of all line quantities |
| `status` | tinyint | `0`=pending, `1`=verified, `2`=cancelled, `3`=paid, `4`=preparing, `5`=picked_up. POS only ever reads `1` (verified) for the pickup queue; the others are dashboard/admin-side states. |
| `note` | string\|null | Customer-provided note |
| `verified_by` | integer\|null | FK to `users` — admin who verified |
| `verified_at` | datetime\|null | When verified |
| `cancelled_by` | integer\|null | FK to `users` — admin who cancelled |
| `cancelled_at` | datetime\|null | When cancelled |
| `created_at` | datetime | When the order was placed |
| `updated_at` | datetime | Last update |
| `deleted_at` | datetime\|null | Soft delete timestamp |

### EcommerceOrderLine

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Primary key |
| `ecommerce_order_id` | integer | FK to `ecommerce_orders` |
| `item_id` | integer | FK to `items` |
| `item_name` | string | Snapshot of the item name at order time |
| `qty` | integer | Quantity ordered |
| `price` | decimal(12,2) | Unit price snapshot at order time |
| `sub_total` | decimal(12,2) | `qty * price` |

### Customer (partial)

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Primary key |
| `name` | string | Customer full name |
| `code` | string | Customer code (e.g. `CUST-00005`) |
| `phone` | string | Phone number in canonical `09XXXXXXXXX` form. Unique at the DB level since June 2026 — no two customers share a phone. |
| `phone_verified_at` | datetime\|null | _Added June 2026._ Stamped when the customer proved phone ownership via OTP. `/shop` registration sets this; admin-created customers (POS counter / imports) leave it null. POS can surface a "verified" badge in CRM views. |
| `sms_notifications_enabled` | boolean | _Added June 2026._ Customer opt-in for transactional SMS (order updates, etc.). Default true. POS may surface a toggle if customer-CRM editing lands. |
| `email_verified_at` | datetime\|null | Auto-stamped at `/shop` registration since the phone OTP already proves the human; legacy customers from POS imports may still be null. |

### Item (partial — in order lines)

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Primary key |
| `barcode` | string | Item barcode for POS scanning |
| `name` | string | Current item name |

---

## Order Status Lifecycle

Extended 2026-06-07 to include the post-payment states. The POS still
only **reads** verified orders — everything downstream of POS ring-up
(paid → preparing → picked_up) flows from the back office or the POS
sale itself auto-advancing.

```
Customer       Admin           POS rings up         Back-office       Counter
                                                      coordination
   │              │                  │                   │                │
   ▼              ▼                  ▼                   ▼                ▼
┌────────┐  ┌──────────┐      ┌─────────┐         ┌──────────┐    ┌──────────┐
│Pending │─▶│ Verified │─────▶│  Paid   │────────▶│Preparing │───▶│ Picked   │
│  (0)   │  │   (1)    │      │  (3)    │         │   (4)    │    │ Up (5)   │
└────────┘  └──────────┘      └─────────┘         └──────────┘    └──────────┘
    │
    │ cancel()
    ▼
┌──────────┐
│Cancelled │
│   (2)    │  ←─ terminal (parallel)
└──────────┘
```

- **Pending (0):** Order placed by customer, awaiting admin review. Not visible to POS.
- **Verified (1):** Admin approved the order. **Visible to POS via API** for payment processing. This is the only status the POS app needs to surface for action.
- **Cancelled (2):** Admin rejected the order. Not visible to POS.
- **Paid (3):** Sale has been recorded against this order — either rung up at POS (sale.pos_id NOT NULL) or recorded cashless from the back office / dashboard (sale.pos_id IS NULL). POS sale-creation auto-advances the order to PAID; no separate POS action needed. Cashier sees these as "already paid, ready for fulfilment" — not actionable.
- **Preparing (4):** Back office or dashboard advanced the order. Cashier may see this state if the customer arrives while staff is still packing.
- **Picked Up (5):** Terminal-happy. Order has been collected. Either back office marks this from the dashboard, or — future scope — the POS itself confirms pickup with an optional handover photo.

Status transitions are forward-only past verification. A cancelled order cannot be revived; a paid order cannot go back to verified.

### What the POS does NOT do

The POS app is **read-only** for the ecommerce-order lifecycle. It does NOT:
- Verify pending orders (admin web / dashboard does that)
- Cancel orders (admin web / dashboard does that)
- Record cashless payments (admin web / dashboard does that — this is for remote GCash/bank/cheque receipts)
- Mark preparing (back-office coordination)
- Mark picked up (back-office today; could become POS in a future scope)

The POS DOES advance the order — implicitly, via the existing `POST /api/v1/sales` endpoint when the cashier rings up the order with `ecommerce_order_id` in the request body. No new POS endpoint is needed.

**Critical:** the POS path advances the order **all the way to PICKED_UP**, not just PAID. Customer is at the counter — paying and physically collecting the goods happen in the same action, so the lifecycle telescopes:

```
verified  →  paid  →  picked_up
   │           │          │
   └───────────┴──────────┘
              all three in one POS sale, single server hit
```

Both `verified → paid` AND `paid → picked_up` transitions are written to `ecommerce_order_status_changes` with the cashier as the actor, so the timeline reflects the full path. Cashless / web-admin / dashboard sales (sale.pos_id IS NULL) stop at PAID — back office advances preparing/picked_up manually because the customer isn't there yet.

### Customer-signalled payment intent

`ecommerce_orders.payment_intent` (nullable string) is captured at /shop checkout — the customer picks how they intend to pay. The POS surface should display it on the order detail so the cashier knows what to expect:

| `payment_intent` | Cashier sees |
|---|---|
| `cash_on_pickup` | "Paying: Cash on Pickup" — common case |
| `gcash` | "Paying: GCash" — customer may show transfer reference |
| `bank_transfer` | "Paying: Bank Transfer" — may already be paid (check status) |
| `cheque` | "Paying: Cheque" — bring out the cheque book |
| null | "Decide at pickup" — open conversation |

Intent is **not** money received. It only prefills the cashier's expectation and the admin's Record Payment modal. The customer hasn't paid yet just because they picked an intent — the actual payment is recorded by either the POS sale flow or the back-office Record Payment surface.

### Cashier UX with the new statuses

| Order shows status… | Cashier sees… | Action |
|---|---|---|
| Verified (1) | "Ready to ring up" badge | Load to cart → POS sale flow → server auto-advances to PICKED_UP |
| Paid (3) — cashless | "Already paid (GCash / bank transfer / cheque) — ready for handover" badge | Customer is here to collect. Mark picked up via the back-office surface (or wait for a future POS pickup-confirm action). |
| Preparing (4) | "Being packed" badge | Inform customer to wait briefly |
| Picked Up (5) | "Already collected" badge | None — don't surface for action |
| Cancelled (2) | Hidden (don't surface) | — |

---

## Order Flow (End-to-End)

### 1. Customer Places Order (Web Storefront)

1. Customer browses `/shop/products`, adds items to cart
2. Cart is persisted in database (`carts` + `cart_items` tables)
3. Customer navigates to `/shop/cart`, reviews items
4. Customer clicks "Place Order"
5. System validates:
   - Cart is not empty
   - All items have available stock (`SUM(item_stores.stock) > 0`)
   - Requested quantity does not exceed available stock
6. Within a database transaction:
   - `EcommerceOrder` created with status `0` (pending), generated reference
   - `EcommerceOrderLine` records created with price/name snapshots
   - Cart items cleared
7. Customer redirected to `/customer/orders`

### 2. Admin Verifies/Cancels (Admin Panel)

1. Admin views orders at `/admin/ecommerce-orders`
2. DataTable shows all orders with status filtering
3. Admin clicks into an order to see details + line items
4. Admin either:
   - **Verifies** (`POST /admin/ecommerce-orders/{id}/verify`): sets status=1, records `verified_by` and `verified_at`
   - **Cancels** (`POST /admin/ecommerce-orders/{id}/cancel`): sets status=2, records `cancelled_by` and `cancelled_at`

### 3. POS Retrieves Verified Orders (API)

1. POS app calls `GET /api/v1/ecommerce-orders` to list verified orders
2. Displays orders with customer info, reference, total, line items
3. POS calls `GET /api/v1/ecommerce-orders/{id}` for full order detail
4. POS uses `lines[].item.barcode` to match items for scanning/processing
5. POS processes payment using existing POS sale flow (`POST /api/v1/sales` with `ecommerce_order_id` set)
6. Server-side: `SaleCreationService::advanceLinkedOrderToPaid` flips the order from VERIFIED (1) to PAID (3) and writes the transition to `ecommerce_order_status_changes`. No POS-side action needed.

### 4. Back Office / Dashboard Drives Post-Payment States

For orders paid cashless OR after POS ring-up, the back office (web `/admin/ecommerce-orders/{id}` or apex_dashboard mobile) advances:

- **Paid → Preparing:** packing crew started the order. (Or dashboard owner clicks "Mark Preparing".)
- **Preparing → Picked Up:** customer collected at the counter. Optional photo proofs captured (signed receipt, customer with goods, handover shot).

The POS doesn't initiate these transitions today. Customers showing the QR from `/customer/orders/{ref}` at the counter just opens the admin order page — staff there marks pickup. A future spec could give the POS a "Mark Picked Up" button so the cashier can confirm in one tap, but it's out of scope for the current pass.

---

## Key Design Notes

| Decision | Rationale |
|----------|-----------|
| Separate `EcommerceOrder` model (not POS `Order`) | Ecommerce and POS have different lifecycles and fields |
| Price snapshots in order lines | Prices may change; order records what customer actually agreed to pay |
| Item name snapshot in order lines | Item names may be edited; order preserves original |
| Stock = aggregate sum across stores | `SUM(item_stores.stock)` where `item_stores.status = true` |
| No payment in ecommerce | Payment is handled in-store via the POS app after verification |
| Soft deletes on orders | Audit trail preservation; orders are never truly deleted |
| One cart per customer | `carts.customer_id` has a unique constraint |
| Cart item uniqueness | `cart_items(cart_id, item_id)` unique constraint prevents duplicates |

---

## Error Handling

| HTTP Code | Scenario |
|-----------|----------|
| 200 | Successful request |
| 401 | Missing or invalid Bearer token |
| 404 | Order not found |

---

## Response Format

All responses follow the `ApiResponse` trait format:

**Success:**
```json
{
  "success": true,
  "data": { ... }
}
```

**Error:**
```json
{
  "success": false,
  "message": "Error description"
}
```

---

## Related Files

| File | Purpose |
|------|---------|
| `app/Http/Controllers/API/v1/pos/EcommerceOrderController.php` | POS API controller |
| `app/Models/EcommerceOrder.php` | Order model |
| `app/Models/EcommerceOrderLine.php` | Order line model |
| `app/Models/Cart.php` | Cart model |
| `app/Models/CartItem.php` | Cart item model |
| `app/Livewire/Ecommerce/CartPage.php` | Cart + place order logic |
| `app/Livewire/Ecommerce/CustomerOrders.php` | Customer order history |
| `app/Http/Controllers/Admin/Ecommerce/EcommerceOrderController.php` | Admin verify/cancel |
| `routes/api/pos.php` | POS API routes |
| `routes/ecommerce.php` | Customer-facing routes |
| `routes/admin.php` | Admin routes |
| `tests/Feature/API/v1/EcommerceOrderApiTest.php` | API tests |
| `tests/Feature/Customer/EcommerceOrderTest.php` | Customer order tests |
| `tests/Feature/Admin/EcommerceOrderManagementTest.php` | Admin management tests |
