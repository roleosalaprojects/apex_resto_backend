# E-Commerce Module - Feature Overview

## Introduction

This document describes the e-commerce module being added to the Apex POS application. The module enables online ordering capabilities while integrating with the existing POS inventory system.

---

## What's Being Added

### 1. Public Online Storefront

A new customer-facing website section at `/shop` where users can:

| Feature | Description |
|---------|-------------|
| **Browse Products** | View all available products with images, prices, and descriptions |
| **Search Products** | Real-time search by product name or barcode |
| **Filter by Category** | Browse products by category |
| **View Product Details** | See full product information including available units (e.g., single vs pack) |

**Access:** Public (anyone can browse without logging in)

---

### 2. Shopping Cart System

Logged-in users get a persistent shopping cart:

| Feature | Description |
|---------|-------------|
| **Add to Cart** | Add products with selected quantity and unit type |
| **Update Quantity** | Increase or decrease item quantities |
| **Remove Items** | Remove individual items from cart |
| **Cart Persistence** | Cart is saved to database, survives logout/login |
| **Mini Cart** | Header icon showing item count for quick access |

**Access:** Requires user login

---

### 3. Checkout Process

Multi-step checkout flow:

| Step | Description |
|------|-------------|
| **1. Delivery Method** | Choose between store pickup or home delivery |
| **2. Address** | Enter or select saved delivery address (for delivery orders) |
| **3. Payment Method** | Select payment type (e-wallet, bank transfer, cash on pickup/delivery) |
| **4. Review Order** | Review items, totals, and confirm order |
| **5. Payment Instructions** | Display payment details (bank account, e-wallet number, etc.) |

**Access:** Requires user login

---

### 4. Order Status Flow

Orders follow this lifecycle:

```
PENDING → PROCESSING → CONFIRMED → PAID → READY → SHIPPED/COMPLETED
    ↓         ↓           ↓         ↓       ↓           ↓
    └─────────┴───────────┴─────────┴───────┴───────────┘
                              ↓
                          CANCELLED
```

| Status | Description | Triggered By |
|--------|-------------|--------------|
| **Pending** | Order created, awaiting employee review | Customer places order |
| **Processing** | Employee is reviewing/calling customer | Employee action |
| **Confirmed** | Order confirmed with customer | Employee confirms |
| **Paid** | Payment verified | Employee verifies payment |
| **Ready** | Order ready for pickup/delivery | Employee marks ready |
| **Shipped** | Order dispatched for delivery | Employee marks shipped |
| **Completed** | Order fulfilled | Employee marks complete |
| **Cancelled** | Order cancelled | Customer or employee |

---

### 5. Payment System

#### Supported Payment Methods

| Method | Description |
|--------|-------------|
| **E-Wallet** | GCash, Maya, etc. - customer sends payment and uploads proof |
| **Bank Transfer** | Customer transfers to store bank account and uploads proof |
| **Cash on Pickup** | Customer pays cash when picking up at store |
| **Cash on Delivery** | Customer pays cash upon delivery |

#### Payment Verification

- Customer uploads payment proof (screenshot/receipt image)
- Employee reviews proof and verifies or rejects
- Upon verification, order status automatically updates to "Paid"

---

### 6. Customer Account Features

Logged-in customers can access their account dashboard:

| Feature | Description |
|---------|-------------|
| **Order History** | View all past and current orders |
| **Order Tracking** | Track status of individual orders |
| **Saved Addresses** | Manage multiple delivery addresses |
| **Set Default Address** | Mark one address as default for faster checkout |

**URL:** `/my-account/orders`, `/my-account/addresses`

---

### 7. Employee Management Interface

New admin section for managing e-commerce orders:

| Feature | Description |
|---------|-------------|
| **Orders Dashboard** | Overview with statistics (pending orders, daily totals, etc.) |
| **Orders List** | Filterable table of all e-commerce orders |
| **Order Management** | View order details, update status, add notes |
| **Payment Verification** | Review and verify/reject payment proofs |

**URL:** `/ecommerce/dashboard`, `/ecommerce/orders`, `/ecommerce/payments`

---

### 8. Transaction Logging

All e-commerce activities are logged for audit purposes:

| Event | What's Logged |
|-------|---------------|
| Order Created | Order details, customer, items |
| Status Changed | Old status → New status, who changed it |
| Payment Submitted | Payment method, amount, reference |
| Payment Verified/Rejected | Verifier, timestamp, reason (if rejected) |
| Order Cancelled | Who cancelled, cancellation reason |

---

### 9. Mobile App API

Complete REST API for future mobile application:

#### Public Endpoints
- `GET /api/v1/ecommerce/products` - List products
- `GET /api/v1/ecommerce/products/search` - Search products
- `GET /api/v1/ecommerce/categories` - List categories
- `GET /api/v1/ecommerce/stores` - List stores (for pickup)

#### Authenticated Endpoints
- **Cart:** Add, update, remove items, clear cart
- **Checkout:** Create order, get payment methods
- **Orders:** View orders, order details, cancel order
- **Payments:** Submit payment proof
- **Addresses:** CRUD for saved addresses

---

## New Database Tables

| Table | Purpose |
|-------|---------|
| `ecommerce_orders` | Store e-commerce orders (separate from POS orders) |
| `ecommerce_order_lines` | Line items for each order |
| `ecommerce_carts` | Shopping carts (one per user) |
| `ecommerce_cart_items` | Items in shopping carts |
| `ecommerce_payments` | Payment records with verification status |
| `ecommerce_transaction_logs` | Audit trail for all e-commerce activities |
| `shipping_addresses` | Customer saved delivery addresses |

---

## New User Permissions

Added to the Role model for employee access control:

| Permission | Description |
|------------|-------------|
| `ecom` | Access to e-commerce management section |
| `ecom_orders_read` | Can view e-commerce orders |
| `ecom_orders_update` | Can update order status and notes |
| `ecom_payments_verify` | Can verify/reject payment proofs |
| `ecom_settings` | Can manage e-commerce settings |

---

## Integration with Existing System

### Inventory Integration

- E-commerce uses the existing `Item` model (shared product catalog)
- Stock levels from `ItemStore` are checked during checkout
- Same product can be sold via POS and e-commerce

### User Integration

- E-commerce customers use the existing `User` model
- Existing customers can log in and place orders
- New customers can register via the shop

### Store Integration

- E-commerce orders are assigned to a `Store`
- Customers can select pickup location from existing stores

---

## New Routes Summary

### Public Routes (No Login Required)
| URL | Purpose |
|-----|---------|
| `/shop` | Product catalog homepage |
| `/shop/category/{id}` | Products by category |
| `/shop/product/{id}` | Product detail page |

### Customer Routes (Login Required)
| URL | Purpose |
|-----|---------|
| `/cart` | Shopping cart page |
| `/checkout` | Checkout process |
| `/my-account/orders` | Order history |
| `/my-account/orders/{id}` | Order details |
| `/my-account/addresses` | Manage addresses |

### Employee Routes (Login + Permission Required)
| URL | Purpose |
|-----|---------|
| `/ecommerce/dashboard` | Orders overview dashboard |
| `/ecommerce/orders` | All orders list |
| `/ecommerce/orders/{id}` | Manage single order |
| `/ecommerce/payments` | Payment verification queue |

---

## Technology Stack

| Component | Technology |
|-----------|------------|
| **Frontend** | Livewire 3 (reactive components) |
| **Styling** | Bootstrap 5 + Tailwind CSS (existing stack) |
| **API** | Laravel REST API with Passport authentication |
| **Real-time Updates** | Livewire reactive properties |

---

## User Journey Examples

### Customer Journey: Placing an Order

1. Customer visits `/shop` and browses products
2. Customer logs in (or registers)
3. Customer adds products to cart
4. Customer proceeds to checkout
5. Customer selects "Delivery" and enters address
6. Customer selects "Bank Transfer" as payment
7. Customer reviews and submits order
8. System shows bank details for payment
9. Customer transfers money and uploads receipt screenshot
10. Customer waits for order confirmation

### Employee Journey: Processing an Order

1. Employee sees new order notification on dashboard
2. Employee opens order and reviews details
3. Employee calls customer to confirm order
4. Employee updates status to "Confirmed"
5. Employee receives payment proof notification
6. Employee verifies payment proof matches amount
7. Employee marks payment as "Verified"
8. Employee prepares order and marks as "Ready"
9. For delivery: Employee marks as "Shipped"
10. Employee marks order as "Completed"

---

## Benefits

| Benefit | Description |
|---------|-------------|
| **Expanded Reach** | Customers can order without visiting the store |
| **Shared Inventory** | Single product catalog for POS and online |
| **Manual Payment Flexibility** | Supports local payment methods common in Philippines |
| **Order Tracking** | Customers always know their order status |
| **Audit Trail** | Complete history of all transactions |
| **Mobile-Ready API** | Future mobile app can use same backend |