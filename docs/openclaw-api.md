# OpenClaw API

Bot-facing API surface consumed by OpenClaw / LeteresBot. Read-mostly with
a small number of write endpoints behind explicit token abilities.

## Core

- **Base URL**: `https://leteres.com/api/v1/openclaw`
- **Auth**: `Authorization: Bearer <token>` on every call
- **Throttle**: 120 req/min per token (configurable via `OPENCLAW_RATE_LIMIT_PER_MINUTE`)

## Token abilities

```
openclaw:read                       all GETs (default for tokens with NULL abilities)
openclaw:expenses:create            POST /expenses
openclaw:expenses:update            PATCH /expenses/{id}
openclaw:expenses:upload-receipt    POST/DELETE /expenses/{id}/receipt
openclaw:expenses:void              POST /expenses/{id}/void
openclaw:expense-categories:write   POST /expenses/categories, PATCH /expenses/categories/{id}
openclaw:items:write                PATCH /items/{id}/alert
openclaw:banks:write                PATCH /banks/{id}/alert + transaction proofs
openclaw:banks:adjust               POST /banks/{id}/adjustment (reconciliation)
openclaw:suppliers:write            PATCH /suppliers/{id}/payment-terms
openclaw:purchases:approve          POST /purchases/{id}/approve, /reject
openclaw:purchases:pay              POST /purchases/{id}/pay
openclaw:purchases:void-payment     POST /purchases/{id}/payments/{payment}/void
openclaw:settings:write             PATCH /settings
*                                   wildcard, all current and future
```

Mint a token:

```
vendor/bin/sail artisan openclaw:token-create <user_id> "ChatBot" \
  --abilities="openclaw:read,openclaw:expenses:create,openclaw:expenses:void"
# or for full access:
vendor/bin/sail artisan openclaw:token-create <user_id> "ChatBot" --abilities="*"
```

The plain token is shown once on creation and never stored. Other CLI:
`openclaw:token-list`, `openclaw:token-revoke {id}`.

## Endpoints

### Snapshot

| Method | Path | Ability |
|---|---|---|
| GET | `/snapshot` | `openclaw:read` |

One-shot business health: today/yesterday/MTD sales+profit+refunds, top
product today, low-stock counts, customer credit/points totals.

### Sales

| Method | Path | Ability |
|---|---|---|
| GET | `/sales/summary?date_from&date_to&store_id` | `openclaw:read` |
| GET | `/sales/by-item?date_from&date_to&store_id&limit` | `openclaw:read` |
| GET | `/sales/refunds?date_from&date_to&store_id&limit` | `openclaw:read` |

### Inventory

| Method | Path | Ability |
|---|---|---|
| GET | `/inventory/stock?store_id&limit&cursor` | `openclaw:read` |
| GET | `/inventory/low-stock?store_id&limit` | `openclaw:read` |
| GET | `/inventory/suppliers` | `openclaw:read` |
| PATCH | `/items/{item}/alert` | `openclaw:items:write` |

`PATCH alert` body: `{"low_stock_threshold": 100}` or `null` to clear.
Low-stock query uses `COALESCE(items.low_stock_threshold, 10)` so per-item
overrides win and the default is 10.

### Customers

| Method | Path | Ability |
|---|---|---|
| GET | `/customers/top?date_from&date_to&limit` | `openclaw:read` |
| GET | `/customers/outstanding-credit?limit` | `openclaw:read` |
| GET | `/customers/points-summary` | `openclaw:read` |

### Banks

| Method | Path | Ability |
|---|---|---|
| GET | `/banks` (alias for `/banks/accounts`) | `openclaw:read` |
| GET | `/banks/balances` | `openclaw:read` |
| GET | `/banks/accounts?account_type` | `openclaw:read` |
| GET | `/banks/summary` | `openclaw:read` |
| GET | `/banks/transactions?bank_id&type&date_from&date_to&limit` | `openclaw:read` |
| PATCH | `/banks/{bank}/alert` | `openclaw:banks:write` |
| POST | `/banks/transactions/{transaction}/proof` (multipart `proof`) | `openclaw:banks:write` |
| DELETE | `/banks/transactions/{transaction}/proof` | `openclaw:banks:write` |
| POST | `/banks/{bank}/adjustment` | `openclaw:banks:adjust` |

`PATCH alert` body: `{"low_balance_threshold": 5000}`. Bank list/balance
responses include `below_alert: true/false` so the bot can warn without
a second query.

`POST /banks/{bank}/adjustment` reconciles the tracked balance against
reality (passbook, statement, etc.). Records a `bank_transaction` with
an `ADJ-` reference prefix and the supplied reason; type is `Deposit`
when the balance went up, `Withdrawal` when it went down. Atomic. Send
one of:

```json
{ "new_balance": 1855633.09, "reason": "Passbook reconciliation 2026-05-10" }
{ "amount": -1050371.68,    "reason": "Bank fee not previously recorded" }
```

`new_balance` is the recommended shape for reconciliation (the bot
already has the passbook number — let the server compute the delta).
`amount` is the signed delta. Sending exactly the current balance is a
no-op (returns 200 without creating a transaction). The reason field is
required (3–500 chars) for audit. `transaction_date` is optional and
defaults to today.

### Expenses

| Method | Path | Ability |
|---|---|---|
| GET | `/expenses?date_from&date_to&category_id&bank_id&store_id&limit&cursor` | `openclaw:read` |
| GET | `/expenses/summary?period=today\|yesterday\|this_week\|last_week\|this_month\|last_month\|this_year` | `openclaw:read` |
| GET | `/expenses/categories` | `openclaw:read` |
| POST | `/expenses/categories` | `openclaw:expense-categories:write` |
| PATCH | `/expenses/categories/{expenseCategory}` | `openclaw:expense-categories:write` |
| POST | `/expenses` | `openclaw:expenses:create` |
| PATCH | `/expenses/{expense}` | `openclaw:expenses:update` |
| POST | `/expenses/{expense}/void` | `openclaw:expenses:void` |
| POST | `/expenses/{expense}/receipt` (multipart `receipt`) | `openclaw:expenses:upload-receipt` |
| DELETE | `/expenses/{expense}/receipt` | `openclaw:expenses:upload-receipt` |

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

`category` accepts a string name (case-insensitive); use
`expense_category_id` for the numeric id. Unknown name → 422 with the
list of valid categories. `supplier_id` is optional and tags the expense
for the supplier ledger.

**`bank_id` is optional.** Behavior:

- **With `bank_id`** — the bank withdrawal + expense insert run in a
  single DB transaction; the response includes the new bank balance and
  the `bank_transaction` row.
- **Without `bank_id`** — accounting entry only. No `bank_transactions`
  row, no balance change. `data.bank` and `data.bank_transaction` come
  back as `null`. Use this for accruals like payroll where cash already
  left the bank as a single lump sum, or for expenses paid via channels
  Apex doesn't track on its bank ledger.

`PATCH /expenses/{expense}` edits non-financial fields only:
`payee`, `expense_date`, `description`, `receipt_number`,
`expense_category_id` or `category` (string name), `supplier_id`,
`store_id`. Sending `amount`, `bank_id`, or `status` returns 422 with a
"void + recreate" message. Voided expenses are immutable (409). Useful
for fixing typos or attaching a `supplier_id` after the fact.

`POST /expenses/{id}/void` body: `{"reason": "Wrong amount"}` (optional).
Atomic: marks the expense voided and persists
`voided_at` / `voided_by` / `void_reason`. If the expense had a bank
linkage, also creates a `REV-` prefixed deposit on the same bank and
restores the balance; for cashless expenses no reversal is needed and
`data.bank` and `data.reversal_transaction` come back as `null`.
Double-voiding returns 409 without side effects.

`POST /expenses/categories` body:

```json
{
  "name": "Delivery Expense",
  "description": "Trucking, rider payments, delivery fees"
}
```

Category rules:

- `name` required, trimmed, max 255 chars. Uniqueness is enforced
  **platform-wide** (the `expense_categories` table has no tenant
  column today — see `development/recommendations/multi_tenancy_enforcement.md`
  for the open design question). Comparison is case-insensitive via
  the table's default `utf8mb4_unicode_ci` collation.
- `description` optional, max 500 chars.
- Duplicate name → **409** with the existing category in the response
  payload: `{success: false, data: {category: {id, name, description, status}}}`.
- Response on success (**201**): `{success: true, message: "Expense category created.", data: {category: {id, name, description, status}}}`.

`PATCH /expenses/categories/{expenseCategory}` body accepts any subset
of `name`, `description`, `status`:

```json
{
  "name": "Delivery Expense",
  "description": "Updated description",
  "status": true
}
```

- Renaming to a name that another category already uses → **409** with
  the conflicting category in the response.
- Renaming to the row's own current name is a no-op for the name field
  and does not 409.
- Prefer `status: false` for soft-archive over deletion. There is no
  DELETE endpoint — categories already used by expenses must not be
  hard-deleted.
- Unknown category id → **404**.

### Suppliers (ledger)

| Method | Path | Ability |
|---|---|---|
| GET | `/suppliers/payables-summary?limit` | `openclaw:read` |
| GET | `/suppliers/{supplier}/payable` | `openclaw:read` |
| PATCH | `/suppliers/{supplier}/payment-terms` | `openclaw:suppliers:write` |

`PATCH payment-terms` body: `{"payment_terms_days": 30}` or `null`.
Outstanding payable derives on the fly from `purchases`:
`SUM(total - amount_paid) WHERE supplier_id = X AND user_id = tenant
AND approval_status = APPROVED AND total > amount_paid`. Drafts,
rejected POs, and fully-paid POs are excluded — raw POs stay the source
of truth so manually-entered POs flow straight through.

`days_overdue` per PO is `purchased + payment_terms_days < today`. With
no terms set the field is `null`/`0`.

### Purchase orders

| Method | Path | Ability |
|---|---|---|
| GET | `/purchases?approval_status&payment_status&supplier_id&date_from&date_to&limit&cursor` | `openclaw:read` |
| GET | `/purchases/pending-approvals` | `openclaw:read` |
| GET | `/purchases/{purchase}` | `openclaw:read` |
| GET | `/purchases/{purchase}/payments` | `openclaw:read` |
| POST | `/purchases/{purchase}/approve` | `openclaw:purchases:approve` |
| POST | `/purchases/{purchase}/reject` | `openclaw:purchases:approve` |
| POST | `/purchases/{purchase}/pay` | `openclaw:purchases:pay` |
| POST | `/purchases/{purchase}/payments/{payment}/void` | `openclaw:purchases:void-payment` |

`approval_status` values: 0=Draft, 1=Pending, 2=Approved, 3=Rejected.
`payment_status` values: 0=Unpaid, 1=Partial, 2=Paid.

`POST /purchases/{id}/reject` body: `{"rejection_comment": "..."}` (10-1000
chars, required).

`POST /purchases/{id}/pay` body:

```json
{
  "amount": 200000,
  "bank_id": 4,
  "payment_method": "check",
  "check_number": "137286",
  "payment_date": "2026-05-10",
  "notes": "Partial payment"
}
```

`payment_method` accepts either an int (1=Cash, 2=Check, 3=Bank Transfer,
4=E-Wallet) or a case-insensitive string (`cash`, `check`, `bank transfer`,
`e-wallet`). Wraps the bank withdrawal + `purchase_payments` insert in a
DB transaction; the bank balance, the PO's `amount_paid`, and the payment
status update atomically. Refuses payment if the PO isn't approved or if
the amount exceeds the remaining balance.

The mobile API blocks self-approval (you can't approve a PO you created).
OpenClaw deliberately does **not** apply that rule — the bot acts on
behalf of the tenant owner authoritatively, so an owner asking the bot to
approve their own PO is the owner's call, not separation-of-duties.

`POST /purchases/{id}/payments/{payment}/void` body — two shapes:

**(A) Pure unlink** (no `reverse_to_expense` key):

```json
{
  "reason": "Wrong PO"
}
```

Soft-deletes the `purchase_payments` row, calls
`Purchase::recalculatePayments()` so `amount_paid` and `payment_status`
roll back automatically, and **leaves the linked `bank_transactions`
row untouched**. Use this when you'll attribute the cash elsewhere
later (or not at all).

**(B) Reverse-to-expense** (atomic correction with full audit trail):

```json
{
  "reason": "Wrong PO — was utilities expense",
  "reverse_to_expense": {
    "expense": {
      "payee": "Meralco",
      "expense_date": "2026-05-10",
      "category": "Utilities",
      "description": "May 2026 electric bill",
      "receipt_number": "MERALCO-2026-05",
      "supplier_id": null,
      "store_id": null
    },
    "reversal": {
      "description": "Reversing PO #1030 payment — misclassified",
      "payee": "PO Payment Correction",
      "reference_number": "REV-PAY-20260512-ABC123"
    }
  }
}
```

When `reverse_to_expense` is present, the endpoint runs the following
four steps in a **single DB transaction**:

1. Soft-delete the `PurchasePayment` (PO `amount_paid` rolls back).
2. Create a `BankTransaction` deposit on the **same bank** as the
   original payment — restores the bank balance by exactly the
   payment amount. Reversal labels (`description`, `payee`,
   `reference_number`) come from the body. `description` is required;
   `payee` and `reference_number` are optional (reference_number
   defaults to `REV-<original_tx_reference>`).
3. Create the new `Expense` with its own `BankTransaction` withdrawal
   on that same bank — pulls the cash back out, this time
   correctly attributed.
4. The expense amount is implicitly **equal to** the original payment
   amount; not bot-configurable.

End state: the bank's running balance is **unchanged** from the wrong
payment (the cash did leave the bank — once, physically). The
`bank_transactions` table gains **two new rows** (the reversal deposit
and the new expense withdrawal) that together with the original
withdrawal tell the correction story line by line. Four audit_logs
rows are written with `source=openclaw` and the acting `api_token_id`.

Idempotency: voiding an already-voided payment returns 200 with
`data.already_voided: true` AND **skips the reverse_to_expense block**
to prevent double-creating a reversal + expense pair on retry. 404
fires only when the payment doesn't belong to the named PO (or the PO
doesn't belong to the current tenant). 422 fires when the original
payment has no bank linkage (cashless purchase payment — nothing to
reverse) or on validation errors inside the body.

Response on the reverse path:

```json
{
  "success": true,
  "message": "Payment #5 voided. Cash returned to bank and re-recorded as expense.",
  "data": {
    "already_voided": false,
    "payment": { ... },
    "purchase": { "id": 1030, "amount_paid": 0, "payment_status": 0, ... },
    "reversal": { "id": 87, "reference_number": "REV-PAY-...", "amount": 200000, ... },
    "expense": { "id": 412, "reference_number": "EXP-...", "amount": 200000, ... },
    "bank_balance_after_all": 800000.00
  }
}
```

### Analytics & attendance

| Method | Path | Ability |
|---|---|---|
| GET | `/analytics/peak-hours?days&store_id` | `openclaw:read` |
| GET | `/attendance/summary?date_from&date_to&store_id` | `openclaw:read` |
| GET | `/attendance/records?date_from&date_to&user_id&store_id&only_late&limit` | `openclaw:read` |
| GET | `/cash-outs?date_from&date_to&store_id&pos_id&limit` | `openclaw:read` |

`/attendance/summary` is per-employee totals (days_present, days_absent,
days_late, total_late_minutes). `/attendance/records` is row-per-day
with the resolved `employee_name`, `date`, `is_late`, `late_minutes`,
`time_in`, `time_out`, `hours_rendered`, `status` — use it when the bot
needs to surface specific late dates ("April 26: 6 mins late, April 29:
3 mins late"). `only_late=1` filters to is_late rows only.

`/cash-outs` reads from `pos_logs` (type 12 = Cash-Out, type 13 = Void
Cash-Out). Voided cash-outs are excluded automatically: any type=12 row
whose id appears as `so_id` on a type=13 row is filtered out. Tenant
scoping is via the cashier-user join (`pos_logs.user_id` is the
cashier's id, not the tenant). Default window is the last 30 days. Each
row exposes: `id`, `date`, `amount`, `reason`, `employee_name`,
`store_id`, `pos_id`, `so_id`, `created_at`. The bot can sync these
into expenses with `POST /expenses` (no `bank_id` — the cash already
left the till at the POS, no second bank deduction needed).

### Settings

| Method | Path | Ability |
|---|---|---|
| GET | `/settings` | `openclaw:read` |
| PATCH | `/settings` | `openclaw:settings:write` |

`PATCH /settings` is **deep-merge per top-level section**
(`thresholds`, `expense_rules`, `supplier_rules`). Sending one key
inside a section does not clobber its siblings. GET returns the full
shape with documented defaults filled in:

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

Per-item low-stock thresholds and per-bank low-balance thresholds live
on the entities themselves (`items.low_stock_threshold`,
`banks.low_balance_threshold`) — set them via `PATCH /items/{id}/alert`
and `PATCH /banks/{id}/alert`, not via settings.

## Conventions

- All responses are `{"success": true|false, "message"?, "data": {...}}`.
- Dates are ISO-8601 (`Y-m-d` for date-only, full timestamps for
  `created_at`).
- Timezone is the app's default (Asia/Manila); storage is UTC.
- Photo URLs are absolute and served via `asset()` (public disk).
- Status codes:
  - **401** — no/invalid/revoked bearer
  - **403** — bearer is valid but missing the required ability
  - **404** — resource belongs to another tenant or doesn't exist
  - **409** — conflict (e.g. double-void)
  - **422** — validation failure with `errors[]`
  - **429** — rate limited
