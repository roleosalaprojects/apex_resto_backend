# Ecommerce: Cart & Order System

## Overview
Customers browse products → add to cart → place order (no payment). An employee verifies the order. Once verified, the POS app can access it for payment processing.

## Key Decisions
- **New `EcommerceOrder` model** (separate from POS `Order`)
- **Database-persisted cart** (hidden if stock ≤ 0)
- **Full pricing & quantities** visible to customers
- **Stock = aggregate sum** across all stores (`item_stores`)
- **Verified orders** are just flagged — POS app pulls them separately

---

## Phase 1: Database — Models, Migrations, Factories

### New Tables

**`carts`** — one per customer
| Column | Type |
|--------|------|
| id | bigint PK |
| customer_id | FK → customers (unique) |
| timestamps | |

**`cart_items`** — items in a cart
| Column | Type |
|--------|------|
| id | bigint PK |
| cart_id | FK → carts |
| item_id | FK → items |
| qty | int |
| price | decimal(12,2) — snapshot |
| timestamps | |
| unique(cart_id, item_id) | |

**`ecommerce_orders`**
| Column | Type |
|--------|------|
| id | bigint PK |
| reference | string unique (e.g. `ECO-XXXXXXXX`) |
| customer_id | FK → customers |
| total | decimal(12,2) |
| qty | int |
| status | tinyint: 0=pending, 1=verified, 2=cancelled |
| note | text nullable |
| verified_by | FK → users nullable |
| verified_at | timestamp nullable |
| cancelled_by | FK → users nullable |
| cancelled_at | timestamp nullable |
| timestamps + soft deletes | |

**`ecommerce_order_lines`**
| Column | Type |
|--------|------|
| id | bigint PK |
| ecommerce_order_id | FK |
| item_id | FK → items |
| item_name | string (snapshot) |
| qty | int |
| price | decimal(12,2) (snapshot) |
| sub_total | decimal(12,2) |
| timestamps | |

### Files
- 4 migrations, 4 models (`Cart`, `CartItem`, `EcommerceOrder`, `EcommerceOrderLine`), 4 factories

---

## Phase 2: Customer Cart (Livewire)

### Logic
- Must be logged in to add to cart
- If item already in cart, increment qty
- Items with aggregate stock ≤ 0 hidden/disabled
- Stock = `ItemStore::where('item_id', $id)->sum('stock')`

### Livewire Components
- **`AddToCartButton`** — update existing stub: adds item, shows qty stepper if in cart
- **`CartPage`** — new: full cart view, update qty, remove, totals, "Place Order" button
- **`CartIcon`** — new: header badge with cart item count, displayed in shop navbar and customer layout header when customer is logged in

### Routes
- `GET /shop/cart` → CartPage (authenticated customer)

---

## Phase 3: Place Order

### Flow
1. Customer clicks "Place Order" on cart page
2. Validate stock for each item
3. Create `EcommerceOrder` + lines (snapshot prices)
4. Clear cart
5. Redirect to customer orders list

### Livewire Component
- **`CustomerOrders`** — new: list customer's orders with status

### Routes
- `GET /customer/orders` → CustomerOrders (authenticated customer)

### Dashboard Update
- Add "My Cart" and "My Orders" links

---

## Phase 4: Admin Order Management

### Controller
- `app/Http/Controllers/Admin/Ecommerce/EcommerceOrderController.php`
  - `index()`, `show()`, `table()`, `verify()`, `cancel()`

### Views
- `resources/views/admin/ecommerce-orders/index.blade.php` — DataTable list
- `resources/views/admin/ecommerce-orders/show.blade.php` — detail + verify/cancel buttons

### Routes
- `GET /admin/ecommerce-orders` → index
- `GET /admin/ecommerce-orders/{ecommerceOrder}` → show
- `GET /admin/ecommerce-orders/table` → DataTable AJAX
- `POST /admin/ecommerce-orders/{ecommerceOrder}/verify` → verify
- `POST /admin/ecommerce-orders/{ecommerceOrder}/cancel` → cancel

### Sidebar
- Add "Ecommerce Orders" link in admin sidebar menu

---

## Phase 5: POS API Access

### Endpoints
- `GET /api/v1/ecommerce-orders` — list verified orders
- `GET /api/v1/ecommerce-orders/{id}` — order detail with lines

### Controller
- `app/Http/Controllers/API/v1/pos/EcommerceOrderController.php`

---

## Phase 6: Tests

- `tests/Feature/Customer/CartTest.php` — add/update/remove, stock checks
- `tests/Feature/Customer/EcommerceOrderTest.php` — place order, view orders
- `tests/Feature/Admin/EcommerceOrderManagementTest.php` — list, verify, cancel
- `tests/Feature/API/v1/EcommerceOrderApiTest.php` — POS API access

---

## Verification
1. `/shop/products` → add items to cart → cart icon updates
2. `/shop/cart` → adjust qty, remove, place order
3. `/customer/orders` → see order as "Pending"
4. Admin `/admin/ecommerce-orders` → verify order
5. POS API returns verified order
6. All tests pass