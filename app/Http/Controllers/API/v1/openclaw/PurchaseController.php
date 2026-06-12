<?php

namespace App\Http\Controllers\API\v1\openclaw;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Accounting\Bank;
use App\Models\Accounting\BankTransaction;
use App\Models\Accounting\Expense;
use App\Models\Accounting\ExpenseCategory;
use App\Models\InventoryManagement\Purchase;
use App\Models\InventoryManagement\PurchaseApproval;
use App\Models\InventoryManagement\PurchaseLine;
use App\Models\InventoryManagement\PurchasePayment;
use App\Models\Products\ItemStore;
use App\Models\Products\ItemUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Purchase Order endpoints for OpenClaw.
 *
 * Tenant-scoped by auth()->user()->user_id. The bot represents the tenant
 * owner authoritatively, so the self-approval rule that the mobile API
 * enforces (you can't approve your own PO) is intentionally skipped here:
 * if the owner asks the bot to approve a PO they themselves created,
 * that's the owner's call.
 *
 * Payments record both a purchase_payments row AND a bank_transaction
 * (matching the existing mobile flow), so the bank balance, the PO's
 * amount_paid, and the payment ledger all stay in sync atomically.
 */
class PurchaseController extends Controller
{
    use ApiResponse;

    /**
     * GET /v1/openclaw/purchases — list with filters and cursor pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'approval_status' => 'nullable|integer|in:0,1,2,3',
            'payment_status' => 'nullable|integer|in:0,1,2',
            'supplier_id' => 'nullable|integer|min:1',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'limit' => 'nullable|integer|min:1|max:500',
            'cursor' => 'nullable|integer|min:0',
        ]);

        $tenantUserId = (int) auth()->user()->user_id;
        $limit = (int) $request->input('limit', 100);
        $cursor = (int) $request->input('cursor', 0);

        $query = Purchase::query()
            ->with(['supplier:id,name', 'store:id,name', 'creator:id,name'])
            ->where('user_id', $tenantUserId)
            ->when($request->filled('approval_status'), fn ($q) => $q->where('approval_status', (int) $request->input('approval_status')))
            ->when($request->filled('payment_status'), fn ($q) => $q->where('payment_status', (int) $request->input('payment_status')))
            ->when($request->filled('supplier_id'), fn ($q) => $q->where('supplier_id', (int) $request->input('supplier_id')))
            ->when($request->filled('date_from'), fn ($q) => $q->where('purchased', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->where('purchased', '<=', $request->input('date_to')))
            ->when($cursor > 0, fn ($q) => $q->where('id', '<', $cursor))
            ->orderByDesc('id')
            ->limit($limit + 1);

        $rows = $query->get();
        $hasMore = $rows->count() > $limit;
        $items = $rows->take($limit);

        return $this->success([
            'limit' => $limit,
            'next_cursor' => $hasMore ? (int) $items->last()->id : null,
            'purchase_orders' => $items->map(fn (Purchase $p) => $this->present($p))->values(),
        ]);
    }

    /**
     * GET /v1/openclaw/purchases/pending-approvals — short-cut for "what needs my OK".
     */
    public function pendingApprovals(Request $request): JsonResponse
    {
        $tenantUserId = (int) auth()->user()->user_id;

        $purchases = Purchase::query()
            ->with(['supplier:id,name', 'store:id,name', 'creator:id,name'])
            ->where('user_id', $tenantUserId)
            ->where('approval_status', Purchase::APPROVAL_PENDING)
            ->orderBy('id', 'desc')
            ->get();

        return $this->success([
            'count' => $purchases->count(),
            'purchase_orders' => $purchases->map(fn (Purchase $p) => $this->present($p))->values(),
        ]);
    }

    /**
     * GET /v1/openclaw/purchases/{purchase} — full detail with line items + payments.
     */
    public function show(Request $request, Purchase $purchase): JsonResponse
    {
        $this->authorizeTenant($purchase);

        $purchase->load([
            'lines.item:id,name,barcode',
            'lines.unit:id,name',
            'supplier:id,name,payment_terms_days',
            'store:id,name',
            'creator:id,name',
            'receiver:id,name',
            'payments.bank:id,bank_name,account_name',
            'payments.createdBy:id,name',
            'latestApproval.approver:id,name',
        ]);

        return $this->success([
            'purchase_order' => $this->present($purchase) + [
                'lines' => $purchase->lines->map(fn ($line) => [
                    'id' => $line->id,
                    'item_id' => $line->item_id,
                    'item_name' => $line->item?->name,
                    'item_barcode' => $line->item?->barcode,
                    'unit_id' => $line->unit_id,
                    // Prefer the snapshot (taken at order time) over
                    // the live Unit relation, so a unit rename later
                    // doesn't mutate historical PO data. See §3.2 of
                    // the audit + remediation spec.
                    'unit_name' => $line->unit_name ?? $line->unit?->name,
                    'unit_qty' => (float) ($line->unit_qty ?? 1),
                    'qty' => (float) $line->qty,
                    'cost' => round((float) ($line->cost ?? 0), 2),
                    'sub_total' => round((float) ($line->sub_total ?? 0), 2),
                ])->values(),
                'payments' => $purchase->payments->map(fn (PurchasePayment $p) => $this->presentPayment($p))->values(),
                'latest_approval' => $purchase->latestApproval ? [
                    'status' => $purchase->latestApproval->status,
                    'approved_by_name' => $purchase->latestApproval->approver?->name,
                    'approved_at' => $purchase->latestApproval->approved_at?->toIso8601String(),
                    'rejection_comment' => $purchase->latestApproval->rejection_comment,
                ] : null,
            ],
        ]);
    }

    /**
     * POST /v1/openclaw/purchases/{purchase}/approve — approve a pending PO.
     *
     * §C3 of development/specs/purchase_order_audit_and_remediation.md.
     * Unlike the admin web and mobile counterparts, this method does NOT
     * block self-approval (auth user == purchase.created_by). The bot
     * acts as the tenant owner authoritatively — the human at the keyboard
     * IS the chain of authority. See the class docblock above for the
     * full rationale. Any check added here must also extend to the bot's
     * own creator-identity logic, not just the request-time auth user.
     */
    public function approve(Request $request, Purchase $purchase): JsonResponse
    {
        $this->authorizeTenant($purchase);

        if (! $purchase->isPendingApproval()) {
            return response()->json([
                'success' => false,
                'message' => 'This purchase order is not pending approval.',
                'data' => ['current_status' => $purchase->approval_status],
            ], 409);
        }

        $approverId = (int) auth()->id();

        DB::transaction(function () use ($purchase, $approverId) {
            $purchase->update(['approval_status' => Purchase::APPROVAL_APPROVED]);
            PurchaseApproval::create([
                'purchase_id' => $purchase->id,
                'status' => 'approved',
                'approved_by' => $approverId,
                'approved_at' => now(),
            ]);
        });

        $purchase->refresh()->load(['supplier:id,name', 'store:id,name', 'creator:id,name', 'latestApproval.approver:id,name']);

        return $this->success([
            'purchase_order' => $this->present($purchase),
        ], "PO #{$purchase->po} approved.");
    }

    /**
     * POST /v1/openclaw/purchases/{purchase}/reject — reject with required reason.
     */
    public function reject(Request $request, Purchase $purchase): JsonResponse
    {
        $this->authorizeTenant($purchase);

        $validated = $request->validate([
            'rejection_comment' => 'required|string|min:10|max:1000',
        ]);

        if (! $purchase->isPendingApproval()) {
            return response()->json([
                'success' => false,
                'message' => 'This purchase order is not pending approval.',
                'data' => ['current_status' => $purchase->approval_status],
            ], 409);
        }

        $approverId = (int) auth()->id();

        DB::transaction(function () use ($purchase, $approverId, $validated) {
            $purchase->update(['approval_status' => Purchase::APPROVAL_REJECTED]);
            PurchaseApproval::create([
                'purchase_id' => $purchase->id,
                'status' => 'rejected',
                'approved_by' => $approverId,
                'approved_at' => now(),
                'rejection_comment' => $validated['rejection_comment'],
            ]);
        });

        $purchase->refresh()->load(['supplier:id,name', 'store:id,name', 'creator:id,name', 'latestApproval.approver:id,name']);

        return $this->success([
            'purchase_order' => $this->present($purchase),
        ], "PO #{$purchase->po} rejected.");
    }

    /**
     * POST /v1/openclaw/purchases/{purchase}/pay — record a payment.
     *
     * Accepts payment_method as either an int (1=Cash, 2=Check, 3=Bank Transfer,
     * 4=E-Wallet) or a case-insensitive string ('check', 'cash', etc.).
     */
    /**
     * POST /v1/openclaw/purchases/{purchase}/receive — record (partial) receipt.
     *
     * Body: { "lines": [ { "purchase_line_id": int, "qty": float }, ... ] }
     *
     * Mirrors the admin receiveNow flow (PurchaseController.php:513):
     *   - PurchaseLine.received += qty per line
     *   - ItemStore.stock at the PO's store += (qty * unit conversion)
     *   - Purchase.received += sum(qty); received_by = current user; status = 0
     *
     * Differences from admin:
     *   - Bot does NOT update Item.cost. The admin UI gives users a per-line
     *     opt-in checkbox for "update cost from this PO"; the bot can't make
     *     that pricing judgement, so we never touch item cost here.
     *   - PO must be approved (isApproved() === true). Non-approved POs
     *     return 409.
     *
     * No idempotency token — multiple POSTs with the same lines accumulate
     * received qty (matches the admin partial-receive flow). Validation
     * caps qty so received <= ordered per line.
     */
    public function receive(Request $request, Purchase $purchase): JsonResponse
    {
        $this->authorizeTenant($purchase);

        if (! $purchase->isApproved()) {
            return response()->json([
                'success' => false,
                'message' => 'This purchase order must be approved before items can be received.',
                'data' => ['approval_status' => $purchase->approval_status],
            ], 409);
        }

        $validated = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.purchase_line_id' => ['required', 'integer', 'min:1'],
            'lines.*.qty' => ['required', 'numeric', 'min:0.0001'],
        ]);

        $linesById = PurchaseLine::query()
            ->where('purchase_id', $purchase->id)
            ->get()
            ->keyBy('id');

        foreach ($validated['lines'] as $i => $payload) {
            $lineId = (int) $payload['purchase_line_id'];
            $line = $linesById->get($lineId);

            if ($line === null) {
                throw ValidationException::withMessages([
                    "lines.$i.purchase_line_id" => "Purchase line {$lineId} does not belong to this PO.",
                ]);
            }

            $alreadyReceived = (float) ($line->received ?? 0);
            $ordered = (float) ($line->qty ?? 0);
            $remaining = $ordered - $alreadyReceived;

            if ((float) $payload['qty'] > $remaining + 0.0001) {
                throw ValidationException::withMessages([
                    "lines.$i.qty" => "Cannot receive {$payload['qty']}; only {$remaining} remaining on this line (ordered {$ordered}, already received {$alreadyReceived}).",
                ]);
            }
        }

        $totalReceived = DB::transaction(function () use ($validated, $purchase, $linesById) {
            $total = 0.0;

            foreach ($validated['lines'] as $payload) {
                $line = $linesById->get((int) $payload['purchase_line_id']);
                $qty = (float) $payload['qty'];

                $line->update([
                    'received' => (float) ($line->received ?? 0) + $qty,
                ]);

                // §3.2 — prefer the unit_qty snapshot frozen on the
                // PurchaseLine at order time. Fall back to the live
                // ItemUnit row when the snapshot is null or 0 — both
                // shapes indicate "no snapshot recorded" (pre-2025-10-13
                // lines have null; the column's MySQL default-0 covers
                // rows inserted without an explicit value).
                $snapshot = (float) ($line->unit_qty ?? 0);
                $unitConversion = $snapshot > 0
                    ? $snapshot
                    : (float) (ItemUnit::query()
                        ->where('item_id', $line->item_id)
                        ->where('unit_id', $line->unit_id)
                        ->value('qty') ?? 1);

                $itemStore = ItemStore::query()
                    ->where('item_id', $line->item_id)
                    ->where('store_id', $purchase->store_id)
                    ->lockForUpdate()
                    ->first();

                if ($itemStore !== null) {
                    $itemStore->update([
                        'stock' => (float) $itemStore->stock + ($qty * $unitConversion),
                    ]);
                }

                $total += $qty;
            }

            $purchase->update([
                'received' => (float) ($purchase->received ?? 0) + $total,
                'received_by' => (int) auth()->id(),
                'status' => 0,
            ]);

            return $total;
        });

        $purchase->refresh()->load(['supplier:id,name', 'store:id,name', 'creator:id,name', 'receiver:id,name']);

        $linesRefreshed = PurchaseLine::query()
            ->where('purchase_id', $purchase->id)
            ->get(['id', 'item_id', 'qty', 'received']);

        return $this->success([
            'purchase_order' => $this->present($purchase) + [
                'received' => round((float) $purchase->received, 2),
                'received_by_name' => $purchase->receiver?->name,
            ],
            'lines' => $linesRefreshed->map(fn (PurchaseLine $l) => [
                'id' => (int) $l->id,
                'item_id' => (int) $l->item_id,
                'ordered_qty' => round((float) $l->qty, 4),
                'received_qty' => round((float) ($l->received ?? 0), 4),
                'remaining_qty' => round((float) $l->qty - (float) ($l->received ?? 0), 4),
            ])->values(),
            'received_this_call' => round($totalReceived, 4),
        ], sprintf('Received %s items on PO #%s.', rtrim(rtrim(number_format($totalReceived, 4, '.', ''), '0'), '.'), $purchase->po));
    }

    public function pay(Request $request, Purchase $purchase): JsonResponse
    {
        $this->authorizeTenant($purchase);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'bank_id' => 'required|integer|exists:banks,id',
            'payment_date' => 'nullable|date',
            'payment_method' => 'required',
            'check_number' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:500',
        ]);

        if (! $purchase->canAcceptPayment()) {
            return response()->json([
                'success' => false,
                'message' => $purchase->isFullyPaid()
                    ? 'This purchase order is already fully paid.'
                    : 'Only approved purchase orders can accept payments.',
                'data' => [
                    'approval_status' => $purchase->approval_status,
                    'payment_status' => $purchase->payment_status,
                ],
            ], 409);
        }

        $methodId = $this->resolvePaymentMethod($validated['payment_method']);
        $remaining = (float) $purchase->remaining_balance;
        if ((float) $validated['amount'] > $remaining + 0.005) {
            return response()->json([
                'success' => false,
                'message' => 'Payment exceeds the remaining balance on this PO.',
                'data' => ['remaining_balance' => round($remaining, 2)],
            ], 422);
        }

        $bank = Bank::findOrFail($validated['bank_id']);
        $paymentDate = $validated['payment_date'] ?? now(config('app.timezone'))->toDateString();
        $createdBy = (int) auth()->id();

        $balanceBefore = (float) $bank->balance;

        $result = DB::transaction(function () use ($validated, $purchase, $bank, $balanceBefore, $methodId, $paymentDate, $createdBy) {
            $balanceAfter = $balanceBefore - (float) $validated['amount'];

            $bankTransaction = BankTransaction::create([
                'reference_number' => BankTransaction::generateReferenceNumber(),
                'bank_id' => $bank->id,
                'type' => BankTransaction::TYPE_WITHDRAWAL,
                'amount' => $validated['amount'],
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => 'Payment for PO #'.$purchase->po,
                'payee' => $purchase->supplier?->name ?? 'Supplier',
                'transaction_date' => $paymentDate,
                'created_by' => $createdBy,
            ]);

            $bank->update(['balance' => $balanceAfter]);

            $payment = PurchasePayment::create([
                'reference_number' => PurchasePayment::generateReferenceNumber(),
                'purchase_id' => $purchase->id,
                'bank_id' => $bank->id,
                'bank_transaction_id' => $bankTransaction->id,
                'amount' => $validated['amount'],
                'payment_date' => $paymentDate,
                'payment_method' => $methodId,
                'check_number' => $validated['check_number'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'created_by' => $createdBy,
            ]);

            $purchase->amount_paid = ($purchase->amount_paid ?? 0) + (float) $validated['amount'];
            $purchase->save();
            $purchase->updatePaymentStatus();

            return ['payment' => $payment, 'bank_transaction' => $bankTransaction];
        });

        $bank->refresh();
        $purchase->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Payment of '.number_format((float) $validated['amount'], 2).' recorded for PO #'.$purchase->po.'.',
            'data' => [
                'purchase_order' => $this->present($purchase),
                'payment' => $this->presentPayment($result['payment']),
                'bank' => [
                    'id' => $bank->id,
                    'name' => $bank->bank_name,
                    'account_name' => $bank->account_name,
                    'old_balance' => round($balanceBefore, 2),
                    'new_balance' => round((float) $bank->balance, 2),
                ],
            ],
        ], 201);
    }

    /**
     * GET /v1/openclaw/purchases/{purchase}/payments — payment history.
     */
    public function payments(Request $request, Purchase $purchase): JsonResponse
    {
        $this->authorizeTenant($purchase);

        $purchase->load(['payments.bank:id,bank_name,account_name', 'payments.createdBy:id,name']);

        return $this->success([
            'totals' => [
                'total' => round((float) $purchase->total, 2),
                'amount_paid' => round((float) $purchase->amount_paid, 2),
                'remaining_balance' => round((float) $purchase->remaining_balance, 2),
                'payment_status' => (int) $purchase->payment_status,
            ],
            'payments' => $purchase->payments->map(fn (PurchasePayment $p) => $this->presentPayment($p))->values(),
        ]);
    }

    /**
     * POST /v1/openclaw/purchases/{purchase}/payments/{payment}/void
     *
     * Two modes:
     *
     * (1) Pure unlink (default) — body has no `reverse_to_expense` key.
     *     Soft-deletes the PurchasePayment, recalculates the PO, leaves
     *     the bank withdrawal alone. Use when the cash genuinely left
     *     and you'll attribute it elsewhere later.
     *
     * (2) Reverse-to-expense — body contains `reverse_to_expense`.
     *     Atomically: soft-deletes the PurchasePayment, creates a REV
     *     deposit on the same bank (cash visibly returns), and creates
     *     a new Expense with its own withdrawal on that same bank.
     *     Net bank balance change: 0. The ledger ends with three rows
     *     telling the correction story event by event.
     *
     * Idempotent: if the payment is already voided, returns 200 with
     * `already_voided: true` and does NOT execute the reverse_to_expense
     * branch (to prevent double-creating a reversal+expense pair on a
     * retry).
     */
    public function voidPayment(Request $request, Purchase $purchase, int $paymentId): JsonResponse
    {
        $this->authorizeTenant($purchase);

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
            'reverse_to_expense' => 'sometimes|array',
            'reverse_to_expense.expense' => 'required_with:reverse_to_expense|array',
            'reverse_to_expense.expense.payee' => 'required_with:reverse_to_expense|string|max:255',
            'reverse_to_expense.expense.expense_date' => 'required_with:reverse_to_expense|date',
            'reverse_to_expense.expense.expense_category_id' => 'nullable|integer|exists:expense_categories,id',
            'reverse_to_expense.expense.category' => 'nullable|string|max:255',
            'reverse_to_expense.expense.description' => 'nullable|string|max:1000',
            'reverse_to_expense.expense.receipt_number' => 'nullable|string|max:100',
            'reverse_to_expense.expense.supplier_id' => 'nullable|integer|exists:suppliers,id',
            'reverse_to_expense.expense.store_id' => 'nullable|integer|exists:stores,id',
            'reverse_to_expense.reversal' => 'required_with:reverse_to_expense|array',
            'reverse_to_expense.reversal.description' => 'required_with:reverse_to_expense|string|max:500',
            'reverse_to_expense.reversal.payee' => 'nullable|string|max:255',
            'reverse_to_expense.reversal.reference_number' => 'nullable|string|max:100',
        ]);

        // Look up the payment with trashed rows in scope so we can
        // distinguish "already voided" (200, idempotent) from "wrong PO
        // or missing" (404).
        $payment = PurchasePayment::query()
            ->withTrashed()
            ->where('id', $paymentId)
            ->where('purchase_id', $purchase->id)
            ->first();

        if ($payment === null) {
            abort(404, 'Payment not found on this purchase order.');
        }

        if ($payment->trashed()) {
            return $this->success([
                'already_voided' => true,
                'payment' => $this->presentPayment($payment),
                'purchase' => $this->presentPurchaseTotals($purchase),
                'reversal' => null,
                'expense' => null,
            ], 'Payment was already voided. Reverse-to-expense skipped to prevent double-recording.');
        }

        $reverseSpec = $validated['reverse_to_expense'] ?? null;

        if ($reverseSpec !== null) {
            // Reverse mode needs the original payment to have a bank
            // linkage — there's nothing to reverse if it was cashless.
            if ($payment->bank_id === null || $payment->bank_transaction_id === null) {
                throw ValidationException::withMessages([
                    'reverse_to_expense' => 'This payment has no bank movement to reverse. Void without reverse_to_expense, then record the expense separately.',
                ]);
            }
        }

        $reversalTx = null;
        $expense = null;
        $expenseWithdrawalTx = null;
        $bankAfterAll = null;

        DB::transaction(function () use (
            $payment,
            $purchase,
            $reverseSpec,
            &$reversalTx,
            &$expense,
            &$expenseWithdrawalTx,
            &$bankAfterAll,
        ): void {
            // Step 1 — soft-delete the PurchasePayment. Auditable trait
            // writes a 'deleted' row to audit_logs.
            $payment->delete();

            // Step 2 — recalculate the PO.
            $purchase->recalculatePayments();

            if ($reverseSpec === null) {
                return;
            }

            // Step 3 — REV deposit on the original payment's bank,
            // restoring the balance by exactly the payment amount.
            $bank = Bank::query()->lockForUpdate()->findOrFail($payment->bank_id);
            $balanceBeforeRev = (float) $bank->balance;
            $amount = (float) $payment->amount;
            $balanceAfterRev = $balanceBeforeRev + $amount;

            $origTxRef = optional(BankTransaction::query()->find($payment->bank_transaction_id))->reference_number;
            $defaultRevRef = $origTxRef !== null ? 'REV-'.$origTxRef : BankTransaction::generateReferenceNumber();

            $reversalTx = BankTransaction::create([
                'reference_number' => $reverseSpec['reversal']['reference_number'] ?? $defaultRevRef,
                'bank_id' => $bank->id,
                'type' => BankTransaction::TYPE_DEPOSIT,
                'amount' => $amount,
                'balance_before' => $balanceBeforeRev,
                'balance_after' => $balanceAfterRev,
                'description' => $reverseSpec['reversal']['description'],
                'payee' => $reverseSpec['reversal']['payee'] ?? null,
                'transaction_date' => now()->toDateString(),
                'created_by' => (int) auth()->id(),
            ]);
            $bank->update(['balance' => $balanceAfterRev]);

            // Step 4 — new Expense + matching withdrawal on the same bank.
            // The expense amount equals the original payment amount, so the
            // bank balance ends exactly where it started.
            $expenseSpec = $reverseSpec['expense'];
            $categoryId = $expenseSpec['expense_category_id'] ?? $this->resolveCategoryByName($expenseSpec['category'] ?? null);

            $balanceBeforeExp = (float) $bank->fresh()->balance;
            $balanceAfterExp = $balanceBeforeExp - $amount;

            $expenseWithdrawalTx = BankTransaction::create([
                'reference_number' => BankTransaction::generateReferenceNumber(),
                'bank_id' => $bank->id,
                'type' => BankTransaction::TYPE_WITHDRAWAL,
                'amount' => $amount,
                'balance_before' => $balanceBeforeExp,
                'balance_after' => $balanceAfterExp,
                'description' => 'Expense: '.($expenseSpec['description'] ?? $expenseSpec['payee']),
                'payee' => $expenseSpec['payee'],
                'transaction_date' => $expenseSpec['expense_date'],
                'created_by' => (int) auth()->id(),
            ]);
            $bank->update(['balance' => $balanceAfterExp]);

            $expense = Expense::create([
                'reference_number' => Expense::generateReferenceNumber(),
                'expense_category_id' => $categoryId,
                'store_id' => $expenseSpec['store_id'] ?? null,
                'supplier_id' => $expenseSpec['supplier_id'] ?? null,
                'bank_id' => $bank->id,
                'bank_transaction_id' => $expenseWithdrawalTx->id,
                'payee' => $expenseSpec['payee'],
                'amount' => $amount,
                'expense_date' => $expenseSpec['expense_date'],
                'description' => $expenseSpec['description'] ?? null,
                'receipt_number' => $expenseSpec['receipt_number'] ?? null,
                'status' => Expense::STATUS_ACTIVE,
                'created_by' => (int) auth()->id(),
            ]);

            $bankAfterAll = (float) $bank->fresh()->balance;
        });

        $purchase->refresh();
        $payment->refresh();

        $payload = [
            'already_voided' => false,
            'payment' => $this->presentPayment($payment),
            'purchase' => $this->presentPurchaseTotals($purchase),
            'reversal' => $reversalTx !== null ? [
                'id' => $reversalTx->id,
                'reference_number' => $reversalTx->reference_number,
                'amount' => round((float) $reversalTx->amount, 2),
                'description' => $reversalTx->description,
                'payee' => $reversalTx->payee,
            ] : null,
            'expense' => $expense !== null ? [
                'id' => $expense->id,
                'reference_number' => $expense->reference_number,
                'amount' => round((float) $expense->amount, 2),
                'payee' => $expense->payee,
                'expense_category_id' => $expense->expense_category_id,
                'bank_transaction_id' => $expense->bank_transaction_id,
            ] : null,
            'bank_balance_after_all' => $bankAfterAll !== null ? round($bankAfterAll, 2) : null,
        ];

        $msg = $reverseSpec === null
            ? "Payment #{$payment->id} voided. Bank transaction preserved."
            : "Payment #{$payment->id} voided. Cash returned to bank and re-recorded as expense.";

        return $this->success($payload, $msg);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentPurchaseTotals(Purchase $purchase): array
    {
        return [
            'id' => $purchase->id,
            'po' => $purchase->po,
            'amount_paid' => round((float) $purchase->amount_paid, 2),
            'remaining_balance' => round((float) $purchase->remaining_balance, 2),
            'payment_status' => (int) $purchase->payment_status,
        ];
    }

    private function resolveCategoryByName(?string $name): ?int
    {
        if ($name === null || trim($name) === '') {
            return null;
        }

        $category = ExpenseCategory::query()
            ->whereRaw('LOWER(name) = ?', [strtolower(trim($name))])
            ->first();

        if ($category === null) {
            $available = ExpenseCategory::query()->where('status', 1)->pluck('name')->all();
            throw ValidationException::withMessages([
                'reverse_to_expense.expense.category' => "Unknown expense category '{$name}'. Available: ".implode(', ', $available),
            ]);
        }

        return (int) $category->id;
    }

    private function authorizeTenant(Purchase $purchase): void
    {
        if ((int) $purchase->user_id !== (int) auth()->user()->user_id) {
            abort(404);
        }
    }

    /**
     * @return int payment_method id (1..4)
     */
    private function resolvePaymentMethod(int|string $value): int
    {
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            $id = (int) $value;
            abort_unless(in_array($id, [1, 2, 3, 4], true), 422, 'Invalid payment_method id; valid: 1=Cash, 2=Check, 3=Bank Transfer, 4=E-Wallet.');

            return $id;
        }

        return match (strtolower(trim((string) $value))) {
            'cash' => PurchasePayment::METHOD_CASH,
            'check', 'cheque' => PurchasePayment::METHOD_CHECK,
            'bank transfer', 'bank_transfer', 'transfer' => PurchasePayment::METHOD_BANK_TRANSFER,
            'e-wallet', 'ewallet', 'e_wallet' => PurchasePayment::METHOD_EWALLET,
            default => abort(422, "Unknown payment_method '{$value}'. Valid: cash, check, bank transfer, e-wallet."),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Purchase $p): array
    {
        return [
            'id' => $p->id,
            'po' => $p->po,
            'purchased' => $p->purchased instanceof \DateTimeInterface
                ? $p->purchased->format('Y-m-d')
                : (string) $p->purchased,
            'supplier_id' => $p->supplier_id,
            'supplier_name' => $p->supplier?->name,
            'store_id' => $p->store_id,
            'store_name' => $p->store?->name,
            'invoice_no' => $p->invoice_no,
            'total' => round((float) $p->total, 2),
            'amount_paid' => round((float) $p->amount_paid, 2),
            'remaining_balance' => round((float) $p->remaining_balance, 2),
            'approval_status' => (int) $p->approval_status,
            'approval_status_name' => match ((int) $p->approval_status) {
                Purchase::APPROVAL_DRAFT => 'Draft',
                Purchase::APPROVAL_PENDING => 'Pending',
                Purchase::APPROVAL_APPROVED => 'Approved',
                Purchase::APPROVAL_REJECTED => 'Rejected',
                default => 'Unknown',
            },
            'payment_status' => (int) $p->payment_status,
            'payment_status_name' => match ((int) $p->payment_status) {
                Purchase::PAYMENT_UNPAID => 'Unpaid',
                Purchase::PAYMENT_PARTIAL => 'Partial',
                Purchase::PAYMENT_PAID => 'Paid',
                default => 'Unknown',
            },
            'created_by_name' => $p->creator?->name,
            'created_at' => $p->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentPayment(PurchasePayment $p): array
    {
        return [
            'id' => $p->id,
            'reference_number' => $p->reference_number,
            'amount' => round((float) $p->amount, 2),
            'payment_date' => $p->payment_date instanceof \DateTimeInterface
                ? $p->payment_date->format('Y-m-d')
                : (string) $p->payment_date,
            'payment_method' => (int) $p->payment_method,
            'payment_method_name' => $p->payment_method_name,
            'check_number' => $p->check_number,
            'notes' => $p->notes,
            'bank_id' => $p->bank_id,
            'bank_name' => $p->bank?->bank_name,
            'bank_account_name' => $p->bank?->account_name,
            'created_by_name' => $p->createdBy?->name,
            'created_at' => $p->created_at?->toIso8601String(),
        ];
    }
}
