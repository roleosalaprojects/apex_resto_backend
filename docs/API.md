# Apex POS API Documentation

Complete API reference for the Apex POS system. All API endpoints are RESTful and return JSON responses.

## Base URL

```
Production: https://your-domain.com/api/v1
Development: http://localhost/api/v1
```

## Authentication

All protected endpoints require Bearer token authentication. Tokens are obtained through the login endpoints.

### Headers

```http
Authorization: Bearer {access_token}
Content-Type: application/json
Accept: application/json
```

---

## Table of Contents

- [Authentication](#authentication-endpoints)
- [Sales](#sales-management)
- [Items/Products](#itemsproducts-management)
- [Categories](#categories-management)
- [Units](#units-management)
- [Taxes](#taxes-management)
- [Stores](#stores-management)
- [Customers](#customers-management)
- [Orders](#orders-management)
- [Readings (X/Z)](#readings-management)
- [POS Logs](#pos-logs-management)
- [Purchases](#purchases-management)
- [Reports](#reports)
- [Suppliers](#suppliers-management)
- [Calendar](#calendar-management)
- [Response Formats](#response-formats)
- [Error Codes](#error-codes)

---

## Authentication Endpoints

### POS Login

Authenticate a POS terminal user and receive an access token.

```http
POST /api/v1/login
```

**Request Body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| email | string | Yes | User email address |
| password | string | Yes | User password |
| mac | string | Yes | POS terminal MAC address |

**Example Request:**

```json
{
  "email": "user@example.com",
  "password": "password123",
  "mac": "00:1A:2B:3C:4D:5E"
}
```

**Example Response (200 OK):**

```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "user@example.com",
    "role": {
      "id": 1,
      "name": "Admin",
      "pos": 3,
      "rfnd": true,
      "discounts": true
    }
  },
  "pos": {
    "id": 1,
    "name": "POS-001",
    "store_id": 1
  },
  "receipt": {
    "header": "APEX POS STORE",
    "footer": "Thank you for shopping!"
  }
}
```

**Error Response (400 Bad Request):**

```json
{
  "success": false,
  "message": "Incorrect credentials"
}
```

---

### Mobile App Login

```http
POST /api/v1/mobile/login
```

**Request Body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| email | string | Yes | User email address |
| password | string | Yes | User password |

**Example Request:**

```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

**Example Response (200 OK):**

```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "user@example.com",
    "role": {...},
    "stores": [...]
  }
}
```

---

### Register New User

```http
POST /api/v1/register
```

**Request Body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| name | string | Yes | User full name |
| email | string | Yes | Unique email address |
| password | string | Yes | Minimum 8 characters |
| password_confirmation | string | Yes | Must match password |

**Example Request:**

```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

---

### Get Authenticated User

```http
GET /api/v1/user
```

**Headers:** Authorization required

**Example Response:**

```json
{
  "id": 1,
  "name": "John Doe",
  "email": "user@example.com",
  "role": {
    "id": 1,
    "name": "Admin",
    "permissions": {...}
  }
}
```

---

### Higher Access Authentication

Verify user credentials for elevated permissions (refunds, discounts, etc.).

```http
POST /api/v1/auth/higher_access
```

**Request Body:**

```json
{
  "email": "manager@example.com",
  "password": "password123"
}
```

---

### Verify Unique ID

Verify user by unique identifier for specific role-based actions.

```http
POST /api/v1/auth/verify/uniqid
```

**Request Body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| key | string | Yes | User's unique identifier |
| type | string | Yes | Role type (discounts, delete_items, rfnd) |

---

### Logout

```http
GET /api/v1/logout
```

```http
POST /api/v1/mobile/logout
```

**Headers:** Authorization required

**Response:**

```json
{
  "success": true,
  "message": "Successfully logged out"
}
```

---

## Sales Management

### Create Sale

Process a new sale transaction.

```http
POST /api/v1/sales
```

**Request Body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| pos_id | integer | Yes | POS terminal ID |
| type | boolean | Yes | false = sale, true = refund |
| sale_id | integer | No | Original sale ID (for refunds) |
| line | array | Yes | Array of sale line items |
| details | object | Yes | Sale summary details |

**Line Item Structure:**

```json
{
  "qty": 2,
  "price": 150.00,
  "discount": 0,
  "unit": "PCS",
  "unit_id": 1,
  "unit_qty": 1,
  "vatable": 133.93,
  "vat": 16.07,
  "vat_exempt": 0,
  "zero_rated": 0,
  "product": {
    "id": 1,
    "cost": 100.00
  },
  "profit": 50.00,
  "sc_discount": 0,
  "pwd_discount": 0,
  "sp_discount": 0,
  "naac_discount": 0,
  "vat_special_discounts": 0
}
```

**Details Structure:**

```json
{
  "payment_type": 1,
  "reference_number": "",
  "bank_amount": 0,
  "bank_id": null,
  "total": 300.00,
  "cash": 500.00,
  "change": 200.00,
  "profit": 100.00,
  "vatable": 267.86,
  "vat": 32.14,
  "vat_exempt": 0,
  "zero_rated": 0,
  "sc_discount": 0,
  "pwd_discount": 0,
  "sp_discount": 0,
  "naac_discount": 0,
  "vat_special_discounts": 0,
  "special_discount_type": 0,
  "special_discount_name": null,
  "special_discount_id": null,
  "special_discount_tin": null,
  "customer_id": null,
  "points": 0
}
```

**Payment Types:**

| Value | Description |
|-------|-------------|
| 1 | Cash |
| 2 | E-Wallet |
| 3 | Bank Transfer |
| 4 | Credit |
| 5 | Split Payment |

**Example Response:**

```json
{
  "success": true,
  "sale": {
    "id": 1001,
    "or": "0000001001",
    "total": 300.00,
    "type": 0,
    "created_at": "2024-01-15T10:30:00Z",
    "lines": [...]
  }
}
```

---

### Get Receipts by POS

Retrieve sales for a specific POS terminal.

```http
GET /api/v1/sales/{pos_id}
```

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| startDate | date | Yes | Start date (Y-m-d) |
| endDate | date | Yes | End date (Y-m-d) |

**Example:**

```
GET /api/v1/sales/1?startDate=2024-01-01&endDate=2024-01-31
```

---

### Process Refund

```http
POST /api/v1/sales/refund/{sale_id}
```

**Request Body:**

```json
{
  "line": [
    {
      "qty": 1,
      "product": {
        "id": 1
      }
    }
  ]
}
```

**Validation:**

- `line` - Required, array
- `line.*.qty` - Required, numeric, minimum 1
- `line.*.product.id` - Required, must exist in items table

---

### Log Reprint Receipt

```http
POST /api/v1/mobile/log/reprint_receipt
```

---

## Items/Products Management

### List Items (POS)

```http
GET /api/v1/items
```

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| term | string | Search by name or barcode |

**Example Response:**

```json
{
  "data": [
    {
      "id": 1,
      "name": "Product A",
      "barcode": "1234567890123",
      "price": 150.00,
      "cost": 100.00,
      "category": {...},
      "tax": {...},
      "units": [...],
      "stores": [...]
    }
  ]
}
```

---

### Show Item

```http
GET /api/v1/items/{id}
```

**Response includes:**
- Item details
- Units and conversions
- Store stock levels
- Tax information
- Supplier information

---

### Search Items

```http
GET /api/v1/items/search
```

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| term | string | Search keyword |

---

### Get Items by IDs

```http
GET /api/v1/items/get
```

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| ids | array | Array of item IDs |

---

### List Products (Mobile)

```http
GET /api/v1/mobile/products
```

---

### Create Product (Mobile)

```http
POST /api/v1/mobile/products
```

**Request Body:**

```json
{
  "name": "New Product",
  "barcode": "9876543210123",
  "cost": 100.00,
  "markup": 50,
  "price": 150.00,
  "category_id": 1,
  "tax_id": 1,
  "supplier_id": 1,
  "item_units": [
    {
      "qty": 1,
      "price": 150.00,
      "barcode": "9876543210123",
      "unit_id": 1
    }
  ],
  "item_stores": [
    {
      "stock": 100,
      "store_id": 1
    }
  ]
}
```

**Validation:**

| Field | Rules |
|-------|-------|
| name | required, string |
| barcode | nullable, unique |
| cost | required, numeric |
| markup | required, numeric |
| price | required, numeric |

---

### Update Product (Mobile)

```http
PUT /api/v1/mobile/products/{id}
```

---

### Price Checker Search (Desktop)

Public endpoint for price checker displays.

```http
GET /api/desktop/items/search
```

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| term | string | Barcode or product name |

---

## Categories Management

### List Categories

```http
GET /api/v1/categories
GET /api/v1/mobile/categories
```

**Query Parameters (Mobile):**

| Parameter | Type | Description |
|-----------|------|-------------|
| term | string | Search by name |

---

### Create Category

```http
POST /api/v1/categories
POST /api/v1/mobile/categories
```

**Request Body:**

```json
{
  "name": "Electronics"
}
```

**Validation:**
- `name` - Required, unique (where status = true)

**Authorization:** User must have `itms_create` permission

---

### Show Category

```http
GET /api/v1/categories/{id}
```

**Response includes category with associated items.**

---

### Update Category

```http
PUT /api/v1/categories/{id}
PUT /api/v1/mobile/categories/{id}
```

**Request Body:**

```json
{
  "name": "Updated Category Name"
}
```

---

### Delete Category

```http
DELETE /api/v1/categories/{id}
DELETE /api/v1/mobile/categories/{id}
```

**Note:** Soft deletes by setting status to false.

---

## Units Management

### List Units

```http
GET /api/v1/units
GET /api/v1/mobile/units
```

---

### Get Units Array (Mobile)

```http
GET /api/v1/mobile/units/get
```

---

### Create Unit

```http
POST /api/v1/units
POST /api/v1/mobile/units
```

**Request Body:**

```json
{
  "name": "PCS"
}
```

**Validation:**
- `name` - Required, unique (where status = true)

**Authorization:** User must have `itms_create` permission

---

### Show Unit

```http
GET /api/v1/units/{id}
```

---

### Update Unit

```http
PUT /api/v1/units/{id}
PUT /api/v1/mobile/units/{id}
```

---

### Delete Unit

```http
DELETE /api/v1/units/{id}
DELETE /api/v1/mobile/units/{id}
```

---

## Taxes Management

### List Taxes

```http
GET /api/v1/taxes
```

---

### Create Tax

```http
POST /api/v1/taxes
```

**Request Body:**

```json
{
  "name": "VAT",
  "rate": 12
}
```

**Validation:**
- `name` - Required, unique
- `rate` - Required, integer

---

### Show Tax

```http
GET /api/v1/taxes/{id}
```

---

### Update Tax

```http
PUT /api/v1/taxes/{id}
```

---

### Delete Tax

```http
DELETE /api/v1/taxes/{id}
```

---

## Stores Management

### List Stores

```http
GET /api/v1/stores
GET /api/v1/mobile/stores
```

---

### Create Store

```http
POST /api/v1/stores
POST /api/v1/mobile/stores
```

**Request Body:**

```json
{
  "name": "Main Branch",
  "header": "APEX POS - MAIN BRANCH\n123 Main Street\nTel: (02) 123-4567",
  "footer": "Thank you for shopping!\nPlease come again!",
  "tin": "123-456-789-000",
  "vat_reg": true,
  "phone": "021234567",
  "email": "main@apexpos.com"
}
```

---

### Show Store

```http
GET /api/v1/stores/{id}
GET /api/v1/mobile/stores/{id}
```

---

### Update Store

```http
PUT /api/v1/stores/{id}
PUT /api/v1/mobile/stores/{id}
```

---

### Delete Store

```http
DELETE /api/v1/stores/{id}
DELETE /api/v1/mobile/stores/{id}
```

---

## Customers Management

### Search Customers

```http
GET /api/v1/customers/search
```

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| term | string | Search by name or customer code |

**Example Response:**

```json
{
  "data": [
    {
      "id": 1,
      "name": "John Doe",
      "code": "CUST001",
      "phone": "09123456789",
      "points": 150,
      "accumulated_points": 500
    }
  ]
}
```

---

## Orders Management

### List Orders

```http
GET /api/v1/orders
```

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| startDate | date | Filter start date |
| endDate | date | Filter end date |

---

### Create Order

```http
POST /api/v1/orders
```

**Request Body:**

```json
{
  "items": [
    {
      "id": 1,
      "qty": 2
    },
    {
      "id": 2,
      "qty": 1
    }
  ]
}
```

---

### Show Order

```http
GET /api/v1/orders/{id}
```

---

### Search Order Products

```http
GET /api/v1/orders/search
```

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| keyword | string | Search term |

---

## Readings Management

### Get POS Readings

Get combined X and Z reading data for a POS terminal.

```http
GET /api/v1/readings/{pos_id}
```

---

### Generate X-Reading

```http
GET /api/v1/xreadings/generate/{pos_id}
```

**Apex Format:**

```http
GET /api/v1/xreadings/apex/generate/{pos_id}
```

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| type | string | Optional reading type |

---

### Save X-Reading

```http
POST /api/v1/xreadings/save/{pos_id}
```

**Request Body:**

```json
{
  "reading_at": "2024-01-15",
  "start_at": "2024-01-15 08:00:00",
  "end_at": "2024-01-15 17:00:00",
  "beginning_or": "0000001000",
  "ending_or": "0000001050",
  "opening_fund": 1000.00,
  "cash": 15000.00,
  "e_wallet": 5000.00,
  "refunds": 500.00,
  "withdrawals": 0,
  "cash_in_drawer": 15500.00,
  "user_id": 1,
  "pos_id": 1,
  "store_id": 1
}
```

---

### List X-Readings

```http
GET /api/v1/xreadings
```

---

### Show X-Reading

```http
GET /api/v1/xreadings/{id}
```

---

### Save Z-Reading

End of day reading with full reconciliation.

```http
POST /api/v1/zreadings/save/{pos_id}
```

**Request Body:**

```json
{
  "reading_at": "2024-01-15",
  "start_at": "2024-01-15 08:00:00",
  "end_at": "2024-01-15 22:00:00",
  "beginning_or": "0000001000",
  "ending_or": "0000001100",
  "reset_counter": 1,
  "z_counter": 15,
  "gross_sales": 50000.00,
  "net_sales": 44642.86,
  "vatable_sales": 44642.86,
  "vat_amount": 5357.14,
  "vat_exempt_sales": 0,
  "zero_rated_sales": 0,
  "regular_discount": 1000.00,
  "sc_discount": 500.00,
  "pwd_discount": 200.00,
  "sp_discount": 0,
  "naac_discount": 0,
  "refunds": 300.00,
  "void": 0,
  "cash_sales": 35000.00,
  "e_wallet_sales": 15000.00,
  "denom_1000": 30,
  "denom_500": 10,
  "denom_200": 5,
  "denom_100": 20,
  "denom_50": 10,
  "denom_20": 15,
  "denom_10": 10,
  "denom_5": 5,
  "denom_1": 20,
  "denom_cents": 50,
  "cash_count": 35500.00,
  "short_over": 0,
  "opening_fund": 1000.00,
  "pos_id": 1,
  "store_id": 1,
  "user_id": 1
}
```

---

### List Z-Readings

```http
GET /api/v1/zreadings
```

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| pos_id | integer | Filter by POS terminal |

---

### Show Z-Reading

```http
GET /api/v1/zreadings/{id}
```

---

## POS Logs Management

### Create POS Log

Log cash drawer activities.

```http
POST /api/v1/pos_logs
```

**Request Body:**

```json
{
  "cash_in": 1000.00,
  "cash_out": 0,
  "rendered": 0,
  "type": "cash_in",
  "reason": "Opening fund",
  "so_id": null,
  "pos_id": 1,
  "store_id": 1,
  "user_id": 1
}
```

---

### List POS Logs

```http
GET /api/v1/pos_logs
```

---

### Show POS Log

```http
GET /api/v1/pos_logs/{id}
```

---

## Purchases Management

### List Purchases

```http
GET /api/v1/mobile/purchases
```

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| term | string | Search by PO number |

---

### Create Purchase

```http
POST /api/v1/mobile/purchases
```

**Request Body:**

```json
{
  "supplier_id": 1,
  "store_id": 1,
  "purchased": "2024-01-15",
  "expect": 0,
  "invoice_no": "INV-2024-001",
  "items": 5,
  "total": 10000.00,
  "status": true,
  "received": 0,
  "user_id": 1,
  "created_by": 1,
  "lines": [
    {
      "product_id": 1,
      "unit_id": 1,
      "unit_qty": 1,
      "unit_name": "PCS",
      "qty": 100,
      "price": 50.00,
      "sub_total": 5000.00
    },
    {
      "product_id": 2,
      "unit_id": 1,
      "unit_qty": 1,
      "unit_name": "PCS",
      "qty": 100,
      "price": 50.00,
      "sub_total": 5000.00
    }
  ]
}
```

**Validation:**

| Field | Rules |
|-------|-------|
| supplier_id | required, exists:suppliers,id |
| store_id | required, exists:stores,id |
| purchased | required, date |
| items | required, numeric |
| total | required, numeric |
| received | required, numeric |

**Authorization:** User must have `prchs_create` permission

---

### Show Purchase

```http
GET /api/v1/mobile/purchases/{id}
```

---

### Update Purchase

```http
PUT /api/v1/mobile/purchases/{id}
```

---

### Delete Purchase

```http
DELETE /api/v1/mobile/purchases/{id}
```

---

## Reports

### Sales Summary Report

```http
GET /api/v1/mobile/sales-summary
```

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| startDate | date | Yes | Start date (Y-m-d) |
| endDate | date | Yes | End date (Y-m-d) |
| store_select | integer | No | Filter by store |

**Authorization:** User must have `sls` permission

**Example Response:**

```json
{
  "sales": {
    "sales": 150000.00,
    "revenue": 45000.00,
    "refunds": 2000.00
  },
  "chart": [
    {
      "time": "Jan 01, 24",
      "sales": 10000.00,
      "refunds": 100.00,
      "revenue": 3000.00,
      "receipts": 50
    }
  ],
  "receipts": 500
}
```

---

### Sales by Item Report

```http
GET /api/v1/mobile/sales-by-item
```

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| startDate | date | Yes | Start date |
| endDate | date | Yes | End date |

**Example Response:**

```json
{
  "success": true,
  "items": [
    {
      "item": "Product A",
      "item_id": 1,
      "items_sold": 150,
      "net_sales": 22500.00,
      "revenue": 7500.00
    }
  ]
}
```

---

### Reports Index (POS)

```http
GET /api/v1/reports/index
```

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| startDate | date | Start date |
| endDate | date | End date |
| store_select | integer | Filter by store |

---

## Suppliers Management

### List Suppliers

```http
GET /api/v1/mobile/suppliers
```

**Example Response:**

```json
{
  "data": [
    {
      "id": 1,
      "name": "ABC Supplies",
      "contact": "John Smith",
      "number": "09123456789",
      "email": "abc@supplies.com",
      "address": "123 Business St",
      "city": "Manila",
      "province": "Metro Manila"
    }
  ]
}
```

---

## Calendar Management

### Get Calendar Events

```http
GET /api/v1/mobile/calendar/events
```

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| start | date | Start date |
| end | date | End date |

---

## Role-Based Authentication

### Get Users with Specific Role Permission

```http
GET /api/v1/authentications/roles
```

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| role | string | Yes | Permission type |

**Valid Role Values:**
- `discounts` - Users who can apply discounts
- `delete_items` - Users who can delete items
- `rfnd` - Users who can process refunds

---

### Create Authentication Request

```http
POST /api/v1/authentications
```

**Request Body:**

```json
{
  "pos_id": 1,
  "requested_by": 1,
  "auth_type": "refund",
  "consignee_id": 2
}
```

---

## Response Formats

### Success Response

```json
{
  "success": true,
  "message": "Operation completed successfully",
  "data": { }
}
```

### List Response

```json
{
  "data": [ ],
  "meta": {
    "current_page": 1,
    "total": 100,
    "per_page": 15
  }
}
```

### Error Response

```json
{
  "success": false,
  "message": "Error description"
}
```

### Validation Error Response (422)

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "field_name": [
      "The field_name is required.",
      "The field_name must be a valid email."
    ]
  }
}
```

---

## Error Codes

| HTTP Code | Description |
|-----------|-------------|
| 200 | Success |
| 201 | Created |
| 400 | Bad Request - Invalid parameters |
| 401 | Unauthorized - Invalid or missing token |
| 403 | Forbidden - Insufficient permissions |
| 404 | Not Found - Resource doesn't exist |
| 422 | Validation Error - Invalid input data |
| 500 | Server Error |

---

## Rate Limiting

API requests are rate-limited to prevent abuse. Current limits:

- **Authenticated requests:** 60 requests per minute
- **Unauthenticated requests:** 30 requests per minute

Rate limit headers are included in responses:

```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 59
```

---

## Webhooks

Currently, webhooks are not implemented. Future versions may include webhooks for:

- Sale completed
- Refund processed
- Stock level alerts
- End of day reports

---

## Changelog

### v1.0.0
- Initial API release
- POS authentication and sales
- Product management
- Customer management
- Reading generation (X/Z)
- Reports and analytics

---

## Support

For API support and questions, contact the development team.
