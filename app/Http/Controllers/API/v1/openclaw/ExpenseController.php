<?php

namespace App\Http\Controllers\API\v1\openclaw;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Accounting\Bank;
use App\Models\Accounting\BankTransaction;
use App\Models\Accounting\Expense;
use App\Models\Accounting\ExpenseCategory;
use App\Services\ReceiptStorage;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Read + create endpoints for Apex expenses, exposed to OpenClaw.
 *
 * As with the openclaw banking endpoints, expenses and expense_categories
 * have no user_id column anywhere in this codebase, so the data is global
 * to the installation and matches the existing mobile API behavior. The
 * created_by FK is populated from the resolved openclaw user (the tenant
 * owner) for audit traceability.
 */
class ExpenseController extends Controller
{
    use ApiResponse;

    /**
     * GET /v1/openclaw/expenses — list with filters and cursor-friendly pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'category_id' => 'nullable|integer|min:1',
            'bank_id' => 'nullable|integer|min:1',
            'store_id' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:500',
            'cursor' => 'nullable|integer|min:0',
        ]);

        $tz = config('app.timezone');
        $to = $request->filled('date_to')
            ? Carbon::parse($request->input('date_to'), $tz)->toDateString()
            : Carbon::today($tz)->toDateString();
        $from = $request->filled('date_from')
            ? Carbon::parse($request->input('date_from'), $tz)->toDateString()
            : Carbon::today($tz)->subDays(29)->toDateString();
        $limit = (int) $request->input('limit', 100);
        $cursor = (int) $request->input('cursor', 0);

        $query = Expense::query()
            ->active()
            ->with(['category:id,name', 'bank:id,bank_name', 'store:id,name', 'supplier:id,name'])
            ->whereBetween('expense_date', [$from, $to])
            ->when($request->filled('category_id'), fn ($q) => $q->where('expense_category_id', (int) $request->input('category_id')))
            ->when($request->filled('bank_id'), fn ($q) => $q->where('bank_id', (int) $request->input('bank_id')))
            ->when($request->filled('store_id'), fn ($q) => $q->where('store_id', (int) $request->input('store_id')))
            ->when($cursor > 0, fn ($q) => $q->where('id', '<', $cursor))
            ->orderByDesc('id')
            ->limit($limit + 1);

        $rows = $query->get();
        $hasMore = $rows->count() > $limit;
        $items = $rows->take($limit);
        $nextCursor = $hasMore ? (int) $items->last()->id : null;

        return $this->success([
            'date_from' => $from,
            'date_to' => $to,
            'limit' => $limit,
            'next_cursor' => $nextCursor,
            'expenses' => $items->map(fn (Expense $e) => $this->present($e))->values(),
        ]);
    }

    /**
     * GET /v1/openclaw/expenses/summary — totals + category and bank breakdowns.
     */
    public function summary(Request $request): JsonResponse
    {
        $period = (string) $request->input('period', 'this_month');
        [$from, $to] = $this->resolvePeriod($period);

        // Explicit table-qualified status here so joins with expense_categories
        // (which also has a `status` column) don't trigger an ambiguous column.
        $base = Expense::query()
            ->where('expenses.status', Expense::STATUS_ACTIVE)
            ->whereBetween('expenses.expense_date', [$from->toDateString(), $to->toDateString()]);

        $totals = (clone $base)
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(expenses.amount), 0) as total')
            ->first();

        $byCategory = (clone $base)
            ->leftJoin('expense_categories', 'expenses.expense_category_id', '=', 'expense_categories.id')
            ->groupBy('expenses.expense_category_id', 'expense_categories.name')
            ->selectRaw('expenses.expense_category_id as category_id, expense_categories.name as category_name, COUNT(*) as count, COALESCE(SUM(expenses.amount), 0) as total')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'category_id' => $r->category_id !== null ? (int) $r->category_id : null,
                'category_name' => $r->category_name ?? 'Uncategorized',
                'count' => (int) $r->count,
                'total' => round((float) $r->total, 2),
            ]);

        $byBank = (clone $base)
            ->leftJoin('banks', 'expenses.bank_id', '=', 'banks.id')
            ->groupBy('expenses.bank_id', 'banks.bank_name')
            ->selectRaw('expenses.bank_id, banks.bank_name, COUNT(*) as count, COALESCE(SUM(expenses.amount), 0) as total')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'bank_id' => $r->bank_id !== null ? (int) $r->bank_id : null,
                'bank_name' => $r->bank_name ?? 'No bank',
                'count' => (int) $r->count,
                'total' => round((float) $r->total, 2),
            ]);

        return $this->success([
            'period' => $period,
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            'totals' => [
                'count' => (int) ($totals->count ?? 0),
                'total' => round((float) ($totals->total ?? 0), 2),
            ],
            'by_category' => $byCategory,
            'by_bank' => $byBank,
        ]);
    }

    /**
     * GET /v1/openclaw/expenses/categories — list of active categories.
     */
    public function categories(Request $request): JsonResponse
    {
        $categories = ExpenseCategory::query()
            ->where('status', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'description'])
            ->map(fn (ExpenseCategory $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'description' => $c->description,
            ]);

        return $this->success([
            'categories' => $categories,
        ]);
    }

    /**
     * POST /v1/openclaw/expenses — record an expense and the matching bank withdrawal.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payee' => 'required|string|max:255',
            'expense_date' => 'required|date',
            // Optional: when null, the expense is recorded as an accounting
            // entry only — no bank withdrawal, no balance change. Useful for
            // accruals like payroll where cash already left the bank as a
            // single lump sum, or for expenses paid via channels Apex
            // doesn't track on its bank ledger.
            'bank_id' => 'nullable|integer|exists:banks,id',
            'expense_category_id' => 'nullable|integer|exists:expense_categories,id',
            'category' => 'nullable|string|max:255',
            'store_id' => 'nullable|integer|exists:stores,id',
            'supplier_id' => 'nullable|integer|exists:suppliers,id',
            'description' => 'nullable|string|max:1000',
            'receipt_number' => 'nullable|string|max:100',
        ]);

        $categoryId = $validated['expense_category_id'] ?? $this->resolveCategoryByName($validated['category'] ?? null);
        $createdBy = (int) auth()->id();

        // Cashless / accrual mode: no bank linkage, no withdrawal.
        if (empty($validated['bank_id'])) {
            $expense = Expense::create([
                'reference_number' => Expense::generateReferenceNumber(),
                'expense_category_id' => $categoryId,
                'store_id' => $validated['store_id'] ?? null,
                'supplier_id' => $validated['supplier_id'] ?? null,
                'bank_id' => null,
                'bank_transaction_id' => null,
                'payee' => $validated['payee'],
                'amount' => $validated['amount'],
                'expense_date' => $validated['expense_date'],
                'description' => $validated['description'] ?? null,
                'receipt_number' => $validated['receipt_number'] ?? null,
                'status' => Expense::STATUS_ACTIVE,
                'created_by' => $createdBy,
            ]);

            $expense->load(['category:id,name', 'store:id,name', 'supplier:id,name']);

            return response()->json([
                'success' => true,
                'message' => 'Expense recorded (accounting entry, no bank movement).',
                'data' => [
                    'expense' => $this->present($expense),
                    'bank' => null,
                    'bank_transaction' => null,
                ],
            ], 201);
        }

        $bank = Bank::findOrFail($validated['bank_id']);
        $balanceBefore = (float) $bank->balance;

        [$expense, $bankTransaction] = DB::transaction(function () use ($validated, $bank, $categoryId, $createdBy, $balanceBefore) {
            $balanceAfter = $balanceBefore - (float) $validated['amount'];

            $bankTransaction = BankTransaction::create([
                'reference_number' => BankTransaction::generateReferenceNumber(),
                'bank_id' => $bank->id,
                'type' => BankTransaction::TYPE_WITHDRAWAL,
                'amount' => $validated['amount'],
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => 'Expense: '.($validated['description'] ?? $validated['payee']),
                'payee' => $validated['payee'],
                'transaction_date' => $validated['expense_date'],
                'created_by' => $createdBy,
            ]);

            $bank->update(['balance' => $balanceAfter]);

            $expense = Expense::create([
                'reference_number' => Expense::generateReferenceNumber(),
                'expense_category_id' => $categoryId,
                'store_id' => $validated['store_id'] ?? null,
                'supplier_id' => $validated['supplier_id'] ?? null,
                'bank_id' => $bank->id,
                'bank_transaction_id' => $bankTransaction->id,
                'payee' => $validated['payee'],
                'amount' => $validated['amount'],
                'expense_date' => $validated['expense_date'],
                'description' => $validated['description'] ?? null,
                'receipt_number' => $validated['receipt_number'] ?? null,
                'status' => Expense::STATUS_ACTIVE,
                'created_by' => $createdBy,
            ]);

            return [$expense, $bankTransaction];
        });

        $expense->load(['category:id,name', 'bank:id,bank_name,account_name', 'store:id,name', 'supplier:id,name']);
        $bank->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Expense recorded.',
            'data' => [
                'expense' => $this->present($expense),
                'bank' => [
                    'id' => $bank->id,
                    'name' => $bank->bank_name,
                    'account_name' => $bank->account_name,
                    'old_balance' => round($balanceBefore, 2),
                    'new_balance' => round((float) $bank->balance, 2),
                ],
                'bank_transaction' => [
                    'id' => $bankTransaction->id,
                    'reference_number' => $bankTransaction->reference_number,
                    'type' => $bankTransaction->type_name,
                    'amount' => round((float) $bankTransaction->amount, 2),
                ],
            ],
        ], 201);
    }

    /**
     * PATCH /v1/openclaw/expenses/{expense} — edit non-financial fields.
     *
     * Editable: description, payee, expense_date, receipt_number,
     * expense_category_id (or category name), supplier_id, store_id.
     *
     * NOT editable here (refused, with reason):
     *   amount, bank_id, status, receipt_photo, supplier_payment links.
     * Changing any of those requires void + recreate. Voided expenses
     * are immutable — return 409.
     */
    public function update(Request $request, Expense $expense): JsonResponse
    {
        if ($expense->isVoided()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot edit a voided expense.',
            ], 409);
        }

        $forbidden = array_intersect(
            array_keys($request->all()),
            ['amount', 'bank_id', 'status', 'receipt_photo', 'voided_at', 'voided_by', 'void_reason'],
        );
        if ($forbidden !== []) {
            return response()->json([
                'success' => false,
                'message' => 'These fields cannot be changed via PATCH; void + recreate the expense instead: '.implode(', ', $forbidden),
            ], 422);
        }

        $validated = $request->validate([
            'payee' => 'sometimes|string|max:255',
            'expense_date' => 'sometimes|date',
            'description' => 'sometimes|nullable|string|max:1000',
            'receipt_number' => 'sometimes|nullable|string|max:100',
            'expense_category_id' => 'sometimes|nullable|integer|exists:expense_categories,id',
            'category' => 'sometimes|nullable|string|max:255',
            'supplier_id' => 'sometimes|nullable|integer|exists:suppliers,id',
            'store_id' => 'sometimes|nullable|integer|exists:stores,id',
        ]);

        if (array_key_exists('category', $validated) && ! array_key_exists('expense_category_id', $validated)) {
            $validated['expense_category_id'] = $this->resolveCategoryByName($validated['category']);
        }
        unset($validated['category']);

        if ($validated === []) {
            $expense->load(['category:id,name', 'bank:id,bank_name,account_name', 'store:id,name', 'supplier:id,name']);

            return $this->success([
                'expense' => $this->present($expense),
            ], 'No editable fields supplied; nothing changed.');
        }

        $expense->forceFill($validated)->save();
        $expense->load(['category:id,name', 'bank:id,bank_name,account_name', 'store:id,name', 'supplier:id,name']);

        return $this->success([
            'expense' => $this->present($expense),
        ], 'Expense updated.');
    }

    /**
     * POST /v1/openclaw/expenses/{expense}/void — reverse an expense.
     *
     * Marks the expense voided, generates a REV-prefixed deposit on the
     * expense's bank to restore the funds, and records voided_at / voided_by /
     * void_reason for audit. Atomic via DB transaction; double-voiding returns
     * 409 without side effects.
     */
    public function void(Request $request, Expense $expense): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        if ($expense->isVoided()) {
            return response()->json([
                'success' => false,
                'message' => 'This expense has already been voided.',
                'data' => [
                    'expense_id' => $expense->id,
                    'voided_at' => $expense->voided_at?->toIso8601String(),
                ],
            ], 409);
        }

        $bank = $expense->bank;
        $voidedBy = (int) auth()->id();
        $reason = $validated['reason'] ?? null;

        // Cashless expense (no bank linkage): just mark voided. No reversal.
        if ($bank === null) {
            $expense->forceFill([
                'status' => Expense::STATUS_VOIDED,
                'voided_at' => now(),
                'voided_by' => $voidedBy,
                'void_reason' => $reason,
            ])->save();

            $expense->load(['category:id,name', 'store:id,name', 'supplier:id,name']);

            return $this->success([
                'expense' => $this->present($expense) + [
                    'voided_at' => $expense->voided_at?->toIso8601String(),
                    'voided_by' => $expense->voided_by,
                    'void_reason' => $expense->void_reason,
                ],
                'bank' => null,
                'reversal_transaction' => null,
            ], 'Expense voided (accounting entry, no bank reversal).');
        }

        $balanceBefore = (float) $bank->balance;

        $reversal = DB::transaction(function () use ($expense, $bank, $balanceBefore, $voidedBy, $reason) {
            $balanceAfter = $balanceBefore + (float) $expense->amount;

            $reversal = BankTransaction::create([
                'reference_number' => $this->generateReversalReference(),
                'bank_id' => $bank->id,
                'type' => BankTransaction::TYPE_DEPOSIT,
                'amount' => $expense->amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => 'Void reversal of expense '.$expense->reference_number
                    .($reason !== null ? ' — '.$reason : ''),
                'payee' => 'System Reversal',
                'transaction_date' => now()->toDateString(),
                'created_by' => $voidedBy,
            ]);

            $bank->update(['balance' => $balanceAfter]);

            $expense->forceFill([
                'status' => Expense::STATUS_VOIDED,
                'voided_at' => now(),
                'voided_by' => $voidedBy,
                'void_reason' => $reason,
            ])->save();

            return $reversal;
        });

        $expense->load(['category:id,name', 'bank:id,bank_name,account_name', 'store:id,name']);
        $bank->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Expense voided.',
            'data' => [
                'expense' => $this->present($expense) + [
                    'voided_at' => $expense->voided_at?->toIso8601String(),
                    'voided_by' => $expense->voided_by,
                    'void_reason' => $expense->void_reason,
                ],
                'bank' => [
                    'id' => $bank->id,
                    'name' => $bank->bank_name,
                    'account_name' => $bank->account_name,
                    'old_balance' => round($balanceBefore, 2),
                    'new_balance' => round((float) $bank->balance, 2),
                ],
                'reversal_transaction' => [
                    'id' => $reversal->id,
                    'reference_number' => $reversal->reference_number,
                    'type' => $reversal->type_name,
                    'amount' => round((float) $reversal->amount, 2),
                ],
            ],
        ]);
    }

    private function generateReversalReference(): string
    {
        return sprintf('REV-%s-%s', now()->format('Ymd'), strtoupper(substr(uniqid(), -6)));
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
                'category' => "Unknown expense category '{$name}'. Available: ".implode(', ', $available),
            ]);
        }

        return $category->id;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolvePeriod(string $period): array
    {
        $tz = config('app.timezone');
        $now = Carbon::now($tz);

        return match ($period) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            'this_week' => [$now->copy()->startOfWeek(), $now->copy()->endOfDay()],
            'last_week' => [$now->copy()->subWeek()->startOfWeek(), $now->copy()->subWeek()->endOfWeek()],
            'last_month' => [$now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth()],
            'this_year' => [$now->copy()->startOfYear(), $now->copy()->endOfDay()],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()],
        };
    }

    /**
     * POST /v1/openclaw/expenses/{expense}/receipt — attach (or replace) a receipt photo.
     */
    public function uploadReceipt(Request $request, Expense $expense, ReceiptStorage $storage): JsonResponse
    {
        $request->validate([
            'receipt' => ReceiptStorage::VALIDATION_RULE,
        ]);

        $oldPath = $expense->receipt_photo;
        $newPath = $storage->store($request->file('receipt'), ReceiptStorage::DIR_EXPENSE_RECEIPTS);

        $expense->forceFill(['receipt_photo' => $newPath])->save();

        // Only delete the old file after the new one is safely stored.
        $storage->delete($oldPath);

        return $this->success([
            'expense' => $this->present($expense),
        ], $oldPath ? 'Receipt replaced.' : 'Receipt attached.');
    }

    /**
     * DELETE /v1/openclaw/expenses/{expense}/receipt — clear the receipt photo.
     */
    public function deleteReceipt(Request $request, Expense $expense, ReceiptStorage $storage): JsonResponse
    {
        if ($expense->receipt_photo === null) {
            return $this->success([
                'expense' => $this->present($expense),
            ], 'No receipt to remove.');
        }

        $storage->delete($expense->receipt_photo);
        $expense->forceFill(['receipt_photo' => null])->save();

        return $this->success([
            'expense' => $this->present($expense),
        ], 'Receipt removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Expense $e): array
    {
        return [
            'id' => $e->id,
            'reference_number' => $e->reference_number,
            'expense_date' => $e->expense_date?->toDateString(),
            'payee' => $e->payee,
            'amount' => round((float) $e->amount, 2),
            'category_id' => $e->expense_category_id,
            'category_name' => $e->category?->name,
            'bank_id' => $e->bank_id,
            'bank_name' => $e->bank?->bank_name,
            'store_id' => $e->store_id,
            'store_name' => $e->store?->name,
            'supplier_id' => $e->supplier_id,
            'supplier_name' => $e->supplier?->name,
            'description' => $e->description,
            'receipt_number' => $e->receipt_number,
            'receipt_photo_url' => $e->receipt_photo_url,
            'status' => $e->status,
            'status_name' => $e->status_name,
            'created_at' => $e->created_at?->toIso8601String(),
        ];
    }
}
