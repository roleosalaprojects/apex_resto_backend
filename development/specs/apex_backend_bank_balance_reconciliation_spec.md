# apex_backend — Bank Balance Reconciliation for POS Cash Flows

**Scope:** The POS records `bank_id` on every e-wallet / bank-transfer transaction (sales, credit payments) but **never updates `banks.balance` or writes a `bank_transactions` ledger row**. Result: a customer pays ₱58,250 via GCash → `customers.credit_balance` is debited, but the GCash account's running balance in the books stays the same. Accountants reconciling end-of-day see money in customer ledgers with no matching bank entry. This spec wires the bank side up so the books balance.

**Status:** Identified by direct inspection of `CustomerCreditController::payment()` (no bank mutation) and `SaleController::store()` (stores `bank_id` + `bank_amount` but no bank-side write). Confirmed by grep: every other money-moving controller (Expense, Purchase, Bank reconciliation, transfers) uses the same four-step pattern; the POS flows are the only ones bypassing it.

**Repo:** `apex_backend` (Laravel)

---

## Step 1 — Branch

```bash
cd apex_backend
git checkout main         # or your trunk
git pull
git checkout -b feature/pos-bank-reconciliation
```

If you're also doing the credit_payment higher-access work (sibling spec `apex_backend_credit_payment_higher_access_spec.md`), feel free to land both on the same branch — they're independent but ship together cleanly.

---

## Background — the existing pattern

Every existing controller that touches money uses this exact four-step dance inside a `DB::transaction`:

```php
$bank = Bank::lockForUpdate()->find($bankId);
$balanceBefore = (float) $bank->balance;
$balanceAfter  = $balanceBefore + $signedAmount;   // signed: + for inflow, - for outflow

$tx = BankTransaction::create([
    'reference_number' => BankTransaction::generateReferenceNumber(),
    'bank_id'          => $bank->id,
    'type'             => $signedAmount >= 0 ? BankTransaction::TYPE_DEPOSIT
                                              : BankTransaction::TYPE_WITHDRAWAL,
    'amount'           => abs($signedAmount),
    'balance_before'   => $balanceBefore,
    'balance_after'    => $balanceAfter,
    'description'      => 'Credit payment from <customer>',  // human-readable
    'payee'            => $customerName ?? $storeName,
    'transaction_date' => now(),
    'created_by'       => auth()->id(),
]);

$bank->update(['balance' => $balanceAfter]);
```

Reference implementations to mirror exactly:
- `App\Http\Controllers\Admin\Accounting\BankController::reconcile()`
- `App\Http\Controllers\Admin\Accounting\ExpenseController` (lines around `$bank->update(['balance' => $balanceAfter])`)
- `App\Http\Controllers\API\v1\openclaw\BankController` (deposit / withdrawal / transfer)
- `App\Http\Controllers\API\v1\openclaw\PurchaseController` (purchase payment + reversal)

`BankTransaction` has no `reference_type` / `reference_id` columns; linkage is by `reference_number` and freeform `description`. The other money flows put a human-readable hint in `description` (e.g. *"Purchase payment for PO-123"*). Follow that convention.

---

## Step 2 — Fix `CustomerCreditController::payment()`

File: `app/Http/Controllers/API/v1/pos/CustomerCreditController.php`

Today the method does:

```php
$transaction = DB::transaction(function () use ($validated, $customer) {
    $customer->lockForUpdate();
    $customer->refresh();

    $newBalance = $customer->credit_balance - $validated['amount'];
    $customer->update(['credit_balance' => $newBalance]);

    return CustomerCreditTransaction::create([...]);
});
```

It must also adjust the bank for e-wallet and bank_transfer payments. **Cheques do NOT auto-adjust** — see Step 4.

```php
$transaction = DB::transaction(function () use ($validated, $customer) {
    $customer->lockForUpdate();
    $customer->refresh();

    $newBalance = $customer->credit_balance - $validated['amount'];
    $customer->update(['credit_balance' => $newBalance]);

    $creditTx = CustomerCreditTransaction::create([
        // ... existing fields ...
    ]);

    // Bump the receiving bank for e-wallet & bank_transfer payments.
    // Cash never touches a bank. Cheques are deferred (Step 4).
    $cleared = in_array($validated['payment_method'], ['e-wallet', 'bank_transfer'], true);
    if ($cleared && !empty($validated['bank_id'])) {
        $bank = Bank::lockForUpdate()->find($validated['bank_id']);
        if ($bank) {
            $before = (float) $bank->balance;
            $after  = $before + (float) $validated['amount'];

            BankTransaction::create([
                'reference_number' => BankTransaction::generateReferenceNumber(),
                'bank_id'          => $bank->id,
                'type'             => BankTransaction::TYPE_DEPOSIT,
                'amount'           => (float) $validated['amount'],
                'balance_before'   => $before,
                'balance_after'    => $after,
                'description'      => 'Credit payment from '.($customer->name ?? 'customer #'.$customer->id)
                                      .' (credit_tx #'.$creditTx->id.')',
                'payee'            => $customer->name,
                'transaction_date' => now(),
                'created_by'       => Auth::guard('api')->id(),
            ]);

            $bank->update(['balance' => $after]);
        }
    }

    return $creditTx;
});
```

The reference to the `CustomerCreditTransaction` id in the description is enough for tracing — no schema change needed.

Imports to add at the top of the file:

```php
use App\Models\Accounting\Bank;
use App\Models\Accounting\BankTransaction;
```

---

## Step 3 — Fix `SaleController::store()` for e-wallet / bank-funded sales

File: `app/Http/Controllers/API/v1/pos/SaleController.php`

Looking at the existing sale row:

- `payment_type = 1` → cash (no bank)
- `payment_type = 2` → e-wallet (entire `total` flows through the bank specified by `bank_id`)
- `payment_type = 3` → credit (no bank yet; settled later through `CustomerCreditController::payment` — see Step 2)
- `bank_amount` + `bank_id` may be set independently (mixed cash + bank scenario for cash sales — verify by reading the request shape in `processSale()`)

After `$sale = Sale::create([...])` succeeds **inside** the existing `DB::transaction` in `processSale()`, add:

```php
// Bank-side accounting: increment the receiving bank for any
// non-cash inflow, and reverse for refunds.
$bankAmount = (float) ($request->details['bank_amount'] ?? 0);
if ($sale->payment_type === 2 || $bankAmount > 0) {
    // For pure e-wallet sales the bank captures the full total;
    // for mixed cash+bank it's just bank_amount.
    $signedAmount = $sale->payment_type === 2
        ? (float) $sale->total
        : $bankAmount;

    if ($sale->type) {           // refund (type=true=refund) → reverse
        $signedAmount = -$signedAmount;
    }

    $bankId = $request->details['bank_id'] ?? null;
    if ($bankId && $signedAmount != 0.0) {
        $bank = Bank::lockForUpdate()->find($bankId);
        if ($bank) {
            $before = (float) $bank->balance;
            $after  = $before + $signedAmount;

            BankTransaction::create([
                'reference_number' => BankTransaction::generateReferenceNumber(),
                'bank_id'          => $bank->id,
                'type'             => $signedAmount >= 0
                    ? BankTransaction::TYPE_DEPOSIT
                    : BankTransaction::TYPE_WITHDRAWAL,
                'amount'           => abs($signedAmount),
                'balance_before'   => $before,
                'balance_after'    => $after,
                'description'      => ($sale->type ? 'Refund for sale ' : 'POS sale ')
                                      .$sale->son,
                'payee'            => optional($sale->customer)->name ?? $pos->store->name,
                'transaction_date' => now(),
                'created_by'       => Auth::guard('api')->user()->id ?? null,
            ]);

            $bank->update(['balance' => $after]);
        }
    }
}
```

**Refund-quantity scaling sidebar:** the partial-refund VAT fix we landed earlier scales VAT to the refunded portion. The bank reversal here uses `$sale->total` (the refund's `total`, which already equals the partial-refund amount because of the upstream fix). So bank reversal naturally matches the refund amount — no extra math needed.

Credit sales (`payment_type = 3`) intentionally do nothing here; the bank only moves when the customer later pays the balance via `CustomerCreditController::payment()` (Step 2).

Imports to add at the top of the file (if not already there):

```php
use App\Models\Accounting\Bank;
use App\Models\Accounting\BankTransaction;
```

---

## Step 4 — Cheque clearance: deferred, not automatic

For credit payments paid by `payment_method = 'cheque'` we want to record the credit settlement on the customer ledger immediately (cashier sees the balance reduced) but **defer the bank update** until someone manually marks the cheque as cleared. Reasoning: cheques can bounce, the actual money lands days later, and we don't want phantom bank balances.

### 4.1 — Schema: add `cleared_at` to `customer_credit_transactions`

```bash
./vendor/bin/sail artisan make:migration add_cleared_at_to_customer_credit_transactions
```

```php
public function up(): void
{
    Schema::table('customer_credit_transactions', function (Blueprint $table) {
        // null = not cleared; non-null = the timestamp the bank was credited
        $table->timestamp('cleared_at')->nullable()->after('reference_id')->index();
    });
}

public function down(): void
{
    Schema::table('customer_credit_transactions', function (Blueprint $table) {
        $table->dropColumn('cleared_at');
    });
}
```

For non-cheque payments (cash / e-wallet / bank_transfer) the controller in Step 2 should set `cleared_at = now()` at creation time so they're consistent. For cheques, leave it null.

Update `CustomerCreditTransaction::$fillable` to include `cleared_at`.

### 4.2 — New endpoint: `POST /api/v1/pos/customers/credit-transactions/{id}/clear` (or wherever fits your routing convention)

```php
public function clearCheque(CustomerCreditTransaction $creditTx): JsonResponse
{
    if ($creditTx->payment_method !== 'cheque') {
        return $this->error('Only cheque payments can be cleared this way', 422);
    }
    if ($creditTx->cleared_at !== null) {
        return $this->error('Already cleared on '.$creditTx->cleared_at->toDateTimeString(), 422);
    }
    if (!$creditTx->bank_id) {
        return $this->error('No bank account linked to this cheque', 422);
    }

    DB::transaction(function () use ($creditTx) {
        $bank = Bank::lockForUpdate()->find($creditTx->bank_id);
        $before = (float) $bank->balance;
        $after  = $before + (float) $creditTx->amount;

        BankTransaction::create([
            'reference_number' => BankTransaction::generateReferenceNumber(),
            'bank_id'          => $bank->id,
            'type'             => BankTransaction::TYPE_DEPOSIT,
            'amount'           => (float) $creditTx->amount,
            'balance_before'   => $before,
            'balance_after'    => $after,
            'description'      => 'Cheque '.($creditTx->reference_number ?? '')
                                  .' cleared (credit_tx #'.$creditTx->id.')',
            'payee'            => optional($creditTx->customer)->name,
            'transaction_date' => now(),
            'created_by'       => Auth::guard('api')->id(),
        ]);

        $bank->update(['balance' => $after]);
        $creditTx->update(['cleared_at' => now()]);
    });

    return $this->success(['cleared_at' => $creditTx->fresh()->cleared_at]);
}
```

Add a route in `routes/api/pos.php` (or the appropriate routes file). The POS-side UI for this can be deferred — for now an admin can fire the endpoint from a dashboard list of uncleared cheques.

### 4.3 — Optional: bounce/reversal endpoint

If a cheque bounces after clearance, the existing pattern (e.g. `BankController::reconcile` or a sibling `unclear` action) can negate the deposit:

```php
BankTransaction::create([..., 'type' => TYPE_WITHDRAWAL, 'description' => 'Cheque bounced: <ref>']);
$bank->update(['balance' => $before - $amount]);
$creditTx->update(['cleared_at' => null]);
// And restore the customer's credit_balance.
```

Defer building the UI for this; the SQL pattern is clear and an ops person can do it from a dashboard escape hatch.

---

## Step 5 — Reconciliation of pre-existing rows (one-time)

Since this is going live for the first time, you'll have a stack of historical `customer_credit_transactions` and `sales` rows where the bank never moved. Two choices:

- **Don't backfill.** Treat the bank balances on the day this ships as the new source of truth. Any reconciliation discrepancy was already there; it's not getting worse.
- **Backfill.** Write a one-time artisan command that walks historical e-wallet / bank_transfer credit payments and e-wallet sales, inserts the missing `bank_transactions`, and recomputes each bank's `balance` from the resulting ledger. Risky if any human-entered adjustments have happened to `banks.balance` in the meantime — those would be lost unless you preserve them separately.

Default to "don't backfill" unless you've talked to the accountant. If you do backfill, do it on a staging copy first and diff the resulting balances against the production ones.

---

## Step 6 — Flush OPCache

Same as the other spec — Sail's web container keeps OPCache on. After editing PHP files, restart it:

```bash
docker restart apex_backend-laravel.test-1
```

`./vendor/bin/sail artisan optimize:clear` does NOT flush OPCache.

---

## Step 7 — Tests

At minimum:

- **Credit payment via cash** → `customer_credit_transactions` row created, NO `bank_transactions` row, `bank.balance` unchanged, `cleared_at = now()`.
- **Credit payment via e-wallet** → `customer_credit_transactions` + matching `bank_transactions` deposit row, `bank.balance` increased by the amount, `cleared_at = now()`.
- **Credit payment via cheque** → `customer_credit_transactions` row created, NO `bank_transactions` row, `bank.balance` unchanged, `cleared_at = null`.
- **Cheque clear endpoint** → creates the deposit, bumps balance, sets `cleared_at`. Idempotent: second call returns 422.
- **E-wallet sale** → `bank_transactions` deposit row matches `sales.total`.
- **Refund of an e-wallet sale (partial qty)** → `bank_transactions` withdrawal row equals the refund's `total` (not the original sale's).
- **Credit sale created** → no `bank_transactions` row (settles later through `CustomerCreditController::payment`).
- **Concurrent credit payment + bank withdrawal (race test)** → `lockForUpdate()` keeps the running balance consistent; final balance equals the algebraic sum regardless of ordering.

---

## What does NOT change

- The POS UI for taking credit payments — same form, same `payment_method` options.
- The `customer_credit_transactions` shape — only `cleared_at` is added.
- The X / Z / shift reading aggregations — they read directly from `customer_credit_transactions`, not from `bank_transactions`. The new bank ledger rows are an accounting overlay, not a reading source.
- Existing money-moving controllers (Expense, Purchase, Bank reconciliation) — they're the reference; don't touch them.

---

## Rollout

- **Cheque-clear UI is not blocking.** Until the dashboard / admin UI for "mark cheque cleared" is built, uncleared cheques will sit on `customer_credit_transactions` with `cleared_at = null` and bank balances will be correct (no phantom money). Anyone with DB access can clear them by hand if needed; the endpoint at Step 4.2 is a clean path once you have a button for it.
- **Forward-compatible with existing readings.** Z/X readings already aggregate from `customer_credit_transactions` (our recent work). They don't depend on `bank_transactions` at all — this change is invisible to them.
- **Coordinate with the dashboard team.** If the dashboard renders per-bank running balance, they'll start seeing entries from POS for the first time. Heads-up but no API change required from their side.

---

## Cross-reference

- Sibling spec: `apex_backend_credit_payment_higher_access_spec.md` (`credit_payment` higher-access permission type) — independent but commonly ships together since both touch credit-payment flows.
- POS work the user already did: the X/Z/shift readings now aggregate credit payments (see `apex_pos/lib/controllers/readings/`). That work assumes the bank side will catch up via this spec.
- BankTransaction TYPE_* constants: `app/Models/Accounting/BankTransaction.php` — `TYPE_DEPOSIT = 1`, `TYPE_WITHDRAWAL = 2`, `TYPE_TRANSFER_OUT = 3`, `TYPE_TRANSFER_IN = 4`.
- Reference number format: `BankTransaction::generateReferenceNumber()` produces `TXN-YYYYMMDD-XXXXXX`. Don't reinvent.
