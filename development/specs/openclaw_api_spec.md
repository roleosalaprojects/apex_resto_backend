# OpenClaw API

Bot-facing API surface consumed by OpenClaw / LeteresBot. Read-mostly with a small number of write endpoints behind explicit token abilities.

## Core

- **Base URL**: `https://leteres.com/api/v1/openclaw`
- **Auth**: `Authorization: Bearer <token>` on every call
- **Throttle**: 120 req/min per token (configurable via `OPENCLAW_RATE_LIMIT_PER_MINUTE`)

## Token abilities

```text
openclaw:read                       all GETs (default for tokens with NULL abilities)
openclaw:expenses:create            POST /expenses
openclaw:expenses:update            PATCH /expenses/{id}
openclaw:expenses:upload-receipt    POST/DELETE /expenses/{id}/receipt
openclaw:expenses:void              POST /expenses/{id}/void
openclaw:expense-categories:write   POST /expenses/categories, PATCH /expenses/categories/{id}
openclaw:items:write                PATCH /items/{id}/alert
openclaw:banks:write                PATCH /banks/{id}/alert + transaction proofs
openclaw:banks:adjust               POST /banks/{id}/adjustment (reconciliation)
openclaw:banks:movements            POST /banks/{id}/deposit, /withdrawal, /transfer
openclaw:suppliers:write            PATCH /suppliers/{id}/payment-terms
openclaw:purchases:approve          POST /purchases/{id}/approve, /reject
openclaw:purchases:pay              POST /purchases/{id}/pay
openclaw:purchases:receive          POST /purchases/{id}/receive
openclaw:purchases:void-payment     POST /purchases/{id}/payments/{payment}/void
openclaw:settings:write             PATCH /settings
*                                   wildcard, all current and future
```

## Endpoints

### Snapshot

- `GET /snapshot` — one-shot business health: today/yesterday/MTD sales+profit+refunds, top product today, low-stock counts, customer credit/points totals.

### Sales

- `GET /sales/summary?date_from&date_to&store_id`
- `GET /sales/by-item?date_from&date_to&store_id&limit`
- `GET /sales/refunds?date_from&date_to&store_id&limit`

### Inventory

- `GET /inventory/stock?store_id&limit&cursor`
- `GET /inventory/low-stock?store_id&limit`
- `GET /inventory/suppliers`
- `PATCH /items/{item}/alert` with `{"low_stock_threshold": 100}` or `null`

### Customers

- `GET /customers/top?date_from&date_to&limit`
- `GET /customers/outstanding-credit?limit`
- `GET /customers/points-summary`

### Banks

- `GET /banks` alias for `/banks/accounts`
- `GET /banks/balances`
- `GET /banks/accounts?account_type`
- `GET /banks/summary`
- `GET /banks/transactions?bank_id&type&date_from&date_to&limit`
- `PATCH /banks/{bank}/alert` with `{"low_balance_threshold": 5000}`
- `POST /banks/transactions/{transaction}/proof` multipart `proof`
- `DELETE /banks/transactions/{transaction}/proof`
- `POST /banks/{bank}/adjustment` for reconciliation — body `{"target_balance": 12345.67, "reason": "Bank statement reconciliation"}`. Creates an `ADJ-` transaction restoring the recorded balance to the target.
- `POST /banks/{bank}/deposit` — body `{"amount": 250.00, "payee": "Cash drop from POS", "description": "EOD deposit", "transaction_date": "2026-05-26"}`. Requires `openclaw:banks:movements`. Creates a `TYPE_DEPOSIT` transaction and increases the bank balance. `transaction_date` defaults to today; `payee` and `description` are optional.
- `POST /banks/{bank}/withdrawal` — same body shape. Decreases the balance. Returns **422** if `amount > balance` (no overdraft).
- `POST /banks/{bank}/transfer` — body `{"transfer_to_bank_id": 5, "amount": 1000, "description": "Move to ops", "transaction_date": "2026-05-26"}`. Creates a paired `TYPE_TRANSFER_OUT` on the source and `TYPE_TRANSFER_IN` on the destination in one DB transaction. Reference numbers are paired as `XXX` and `XXX-IN`. Proof attaches to the source-account leg via the existing transaction-proof endpoint.

### Expenses

- `GET /expenses?date_from&date_to&category_id&bank_id&store_id&limit&cursor`
- `GET /expenses/summary?period=today|yesterday|this_week|last_week|this_month|last_month|this_year`
- `GET /expenses/categories`
- `POST /expenses/categories`
- `PATCH /expenses/categories/{category}`
- `POST /expenses`
- `PATCH /expenses/{expense}`
- `POST /expenses/{expense}/void`
- `POST /expenses/{expense}/receipt` multipart `receipt`
- `DELETE /expenses/{expense}/receipt`

`POST /expenses/categories` body:

```json
{
  "name": "Delivery Expense",
  "description": "Delivery fees, trucking, rider payments, and related delivery costs"
}
```

`PATCH /expenses/categories/{category}` body — any subset:

```json
{
  "name": "Delivery Expense",
  "description": "Updated description",
  "status": true
}
```

Expense category rules:

- Requires `openclaw:expense-categories:write`.
- `name` is required, trimmed, max 255 chars. **Uniqueness is platform-wide** (the `expense_categories` table has no tenant column today — same uniqueness rule the admin UI uses). Comparison is case-insensitive via MySQL `utf8mb4_unicode_ci`.
- `description` is optional/nullable, max 500 chars.
- Duplicate name on create returns **409** with the existing category in the response payload — the bot can recover by reusing the existing id.
- Renaming to a name another category already uses returns **409** with the conflicting category in the payload. Renaming to the row's own current name is a no-op on the name field, not a 409.
- Prefer soft-disable via `PATCH ... {"status": false}` instead of delete. **There is no DELETE endpoint** — categories may be referenced by existing expenses.
- Response shape on success: `{"success": true, "message": "Expense category created.", "data": {"category": {"id": 5, "name": "Delivery Expense", "description": "...", "status": true}}}` (HTTP 201 on create, 200 on update).

`POST /expenses` body:

```json
{
  "amount": 1250,
  "payee": "Meralco",
  "expense_date": "2026-05-10",
  "bank_id": 4,
  "category": "Utilities",
  "supplier_id": 7,
  "description": "Electric bill",
  "receipt_number": "OR-12345"
}
```

`PATCH /expenses/{expense}` body accepts the same editable fields, **excluding `amount`, `bank_id`, and `status`** — those return 422 with a "void + recreate" message:

```json
{
  "payee": "Meralco",
  "expense_date": "2026-05-10",
  "category": "Utilities",
  "supplier_id": 7,
  "description": "Electric bill",
  "receipt_number": "OR-12345"
}
```

Expense notes:

- `category` (string name, case-insensitive) and `expense_category_id` (numeric) both work. **`expense_category_id` wins if both are sent.**
- Unknown category returns 422 with the valid category list. The bot can create the category first via `POST /expenses/categories` if it has `openclaw:expense-categories:write`.
- `bank_id` is **optional**. With a bank, create wraps the bank withdrawal + expense insert in one DB transaction. Without a bank, it's a cashless accounting entry — no `bank_transactions` row, no balance change.
- Void marks the expense voided. If it had a bank linkage, also creates a `REV-` deposit on the same bank and restores the balance; for cashless expenses no reversal is needed. Double-void returns 409.

### Suppliers

- `GET /suppliers/payables-summary?limit`
- `GET /suppliers/{supplier}/payable`
- `PATCH /suppliers/{supplier}/payment-terms` with `{"payment_terms_days": 30}` or `null`

Outstanding payable derives from approved purchases where `total > amount_paid`.

### Purchases

- `GET /purchases?status&supplier_id&date_from&date_to&limit&cursor`
- `GET /purchases/pending-approvals`
- `GET /purchases/{purchase}` — full PO with line items
- `GET /purchases/{purchase}/payments` — payment history
- `POST /purchases/{purchase}/approve` — body `{"note": "OK"}`. Requires `openclaw:purchases:approve`. Sets `approval_status = approved` and `approved_by`. 409 if already decided.
- `POST /purchases/{purchase}/reject` — body `{"reason": "Wrong supplier"}`. Same ability. Sets `approval_status = rejected`. 409 if already decided.
- `POST /purchases/{purchase}/pay` — body `{"amount": 5000, "bank_id": 4, "payment_method": "cash|check|bank_transfer", "reference_number": "..."}`. Requires `openclaw:purchases:pay`. Creates a `PurchasePayment` linked to a bank withdrawal in one transaction. 422 if the PO is not approved, 422 if `amount` would over-pay, 409 if already fully paid.
- `POST /purchases/{purchase}/receive` — body `{"lines": [{"purchase_line_id": 12, "qty": 3}, ...]}`. Requires `openclaw:purchases:receive`. Mirrors the admin partial-receive flow: per line, `PurchaseLine.received` increments by `qty`, `ItemStore.stock` at the PO's store increases by `qty * unit conversion`, and `Purchase.received` accumulates the total. 409 if the PO isn't approved; 422 if any `qty` exceeds the remaining quantity on its line, or if a `purchase_line_id` belongs to a different PO. The bot does NOT update `Item.cost` (no equivalent of the admin checkbox).
- `POST /purchases/{purchase}/payments/{payment}/void` — two modes, single endpoint:
  - **Pure unlink**: body `{"reason": "Wrong PO"}` (all fields optional). Soft-deletes the payment, recalculates the PO, leaves the bank withdrawal alone.
  - **Reverse-to-expense**: body includes `reverse_to_expense: {expense: {payee, expense_date, category|expense_category_id, description?, receipt_number?, supplier_id?, store_id?}, reversal: {description, payee?, reference_number?}}`. Atomic 4-step: (1) soft-delete payment, (2) create REV deposit (cash back), (3) create new Expense + its withdrawal (cash out properly), (4) bank balance ends where the wrong payment left it. Expense amount = original payment amount (not bot-configurable). `reversal.description` is required (explicit); `reversal.payee` + `reversal.reference_number` are optional. `reference_number` defaults to `REV-<original_tx_reference>`.
  - Idempotent: already-voided payment returns 200 `data.already_voided=true` and **skips** the reverse block (so retry doesn't double-create). 404 on wrong-PO or other-tenant. 422 if payment had no bank linkage (cashless) or if the body's category name is unknown.
  - Audit: writes 4 `audit_logs` rows on the reverse path (PurchasePayment deleted, REV BankTransaction created, Expense created, expense BankTransaction created) — all `source=openclaw`, all stamped with the acting `api_token_id`.
  - Ability: `openclaw:purchases:void-payment` (gates both modes). The expense creation inside the same transaction is a sub-operation, not a separately-gated capability.

### Cash-outs

- `GET /cash-outs?date_from&date_to&store_id&limit` — list POS cash-out events (read-only). Cash-out *creation* happens at the POS terminal, not via the bot.

### Audit logs

- `GET /audit-logs?date_from&date_to&source&event&auditable_type&user_id&limit&cursor` — cursor-paginated audit feed. Tenant-scoped via `users.user_id` (returns rows for any actor that belongs to the bot's tenant). Defaults to the last 30 days when `date_from`/`date_to` are omitted. `source` is one of `web|openclaw|mobile|pos|console`; `event` is one of `created|updated|deleted|restored|voided|refunded|approved|rejected`. `auditable_type` accepts the fully-qualified class name (e.g. `App\Models\Accounting\Bank`) but the response presents it as the short class name (`Bank`).

### Alerts

- `GET /alerts?approval_age_days` — bundled feed for "things the bot should nag about":
  - `banks_below_threshold` — banks where `balance <= low_balance_threshold` (banking is global, so all banks are surfaced).
  - `pending_approvals` — POs still in `approval_status=pending` that are at least `approval_age_days` old (default 3 days). Tenant-scoped via `Purchase.user_id`.
  - `overdue_credit` — customers with `credit_balance > 0` whose earliest `customer_credit_transactions.due_date` is in the past. Tenant-scoped via `Customer.user_id`. Each row includes `days_overdue` from the earliest unpaid due_date.

### Analytics & attendance

- `GET /analytics/peak-hours?days&store_id`
- `GET /attendance/summary?date_from&date_to&store_id`
- `GET /attendance/records?date_from&date_to&employee_id&store_id` — per-day timestamps with computed lateness

### Settings

- `GET /settings`
- `PATCH /settings`

Settings shape:

```json
{
  "thresholds": {
    "daily_sales_floor": null,
    "daily_sales_check_after": "18:00"
  },
  "expense_rules": {
    "default_expense_bank_id": null,
    "receipt_required_above": null,
    "preferred_categories": []
  },
  "supplier_rules": {
    "default_supplier_id": null,
    "treat_supplier_payments_as_expenses": true
  }
}
```

## Conventions

- Responses: `{"success": true|false, "message"?, "data": {...}}`
- Dates: ISO-8601; date-only as `Y-m-d`
- Timezone: Asia/Manila app default; storage UTC
- Photo URLs absolute via `asset()` public disk
- Status codes: 401 invalid/revoked bearer, 403 missing ability, 404 other tenant/missing resource, 409 conflict, 422 validation, 429 rate limited
- Conflicts (duplicate names, double void, double approve/reject, over-pay against fully-paid PO) consistently return **409**; validation errors return **422**

## Operator notes

OpenClaw token file on this Mac mini: `~/.openclaw/secrets/leteres-api.token`.
Do not print or share the token casually.

Mint a token:

```bash
vendor/bin/sail artisan openclaw:token-create <user_id> "ChatBot" \
  --abilities="openclaw:read,openclaw:expenses:create,openclaw:expenses:void,openclaw:expense-categories:write"
# Or for full access:
vendor/bin/sail artisan openclaw:token-create <user_id> "ChatBot" --abilities="*"
```

The plain token is shown once on creation and never stored. List/revoke: `openclaw:token-list`, `openclaw:token-revoke {id}`.
