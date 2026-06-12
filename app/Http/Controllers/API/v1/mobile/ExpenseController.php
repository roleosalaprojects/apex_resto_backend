<?php

namespace App\Http\Controllers\API\v1\mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\v1\mobile\Expense\StoreRequest;
use App\Http\Resources\ExpenseCategoryResource;
use App\Http\Resources\ExpenseResource;
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

class ExpenseController extends Controller
{
    use ApiResponse;

    /**
     * Get all expenses with filtering and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Expense::with(['category', 'store', 'bank', 'createdBy']);

        // Filter by category
        if ($request->has('category_id')) {
            $query->where('expense_category_id', $request->category_id);
        }

        // Filter by store/branch
        if ($request->has('store_id')) {
            $query->where('store_id', $request->store_id);
        }

        // Filter by bank
        if ($request->has('bank_id')) {
            $query->where('bank_id', $request->bank_id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->whereDate('expense_date', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->whereDate('expense_date', '<=', $request->end_date);
        }

        // Search by reference, payee, or description
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                    ->orWhere('payee', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('receipt_number', 'like', "%{$search}%");
            });
        }

        $perPage = $request->input('per_page', 20);
        $expenses = $query->latest('expense_date')
            ->latest('id')
            ->paginate($perPage);

        return $this->success([
            'expenses' => ExpenseResource::collection($expenses),
            'pagination' => [
                'current_page' => $expenses->currentPage(),
                'last_page' => $expenses->lastPage(),
                'per_page' => $expenses->perPage(),
                'total' => $expenses->total(),
            ],
        ]);
    }

    /**
     * Get a specific expense.
     */
    public function show(Expense $expense): JsonResponse
    {
        $expense->load(['category', 'store', 'bank', 'bankTransaction', 'createdBy', 'approvedBy']);

        return $this->success([
            'expense' => new ExpenseResource($expense),
        ]);
    }

    /**
     * Record a new expense.
     */
    public function store(StoreRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $bank = Bank::findOrFail($validated['bank_id']);

        $expense = DB::transaction(function () use ($validated, $bank) {
            // Create the bank withdrawal transaction
            $balanceBefore = $bank->balance;
            $balanceAfter = $balanceBefore - $validated['amount'];

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
                'created_by' => auth()->id(),
            ]);

            $bank->update(['balance' => $balanceAfter]);

            // Create the expense record
            $expense = Expense::create([
                'reference_number' => Expense::generateReferenceNumber(),
                'expense_category_id' => $validated['expense_category_id'] ?? null,
                'store_id' => $validated['store_id'] ?? null,
                'bank_id' => $bank->id,
                'bank_transaction_id' => $bankTransaction->id,
                'payee' => $validated['payee'],
                'amount' => $validated['amount'],
                'expense_date' => $validated['expense_date'],
                'description' => $validated['description'] ?? null,
                'receipt_number' => $validated['receipt_number'] ?? null,
                'status' => Expense::STATUS_ACTIVE,
                'created_by' => auth()->id(),
            ]);

            return $expense;
        });

        $expense->load(['category', 'store', 'bank', 'createdBy']);

        return $this->created([
            'expense' => new ExpenseResource($expense),
            'bank_new_balance' => $bank->fresh()->balance,
        ], 'Expense of '.number_format($validated['amount'], 2).' recorded successfully.');
    }

    /**
     * Update expense (non-financial fields only).
     */
    public function update(Request $request, Expense $expense): JsonResponse
    {
        if ($expense->isVoided()) {
            return $this->error('Cannot update a voided expense.', 400);
        }

        $validated = $request->validate([
            'expense_category_id' => ['nullable', 'exists:expense_categories,id'],
            'store_id' => ['nullable', 'exists:stores,id'],
            'payee' => ['sometimes', 'required', 'string', 'max:255'],
            'expense_date' => ['sometimes', 'required', 'date'],
            'description' => ['nullable', 'string', 'max:1000'],
            'receipt_number' => ['nullable', 'string', 'max:100'],
        ]);

        $expense->update($validated);
        $expense->load(['category', 'store', 'bank', 'createdBy']);

        return $this->success([
            'expense' => new ExpenseResource($expense),
        ], 'Expense updated successfully.');
    }

    /**
     * Void an expense (reverses the bank transaction).
     */
    public function void(Expense $expense): JsonResponse
    {
        if ($expense->isVoided()) {
            return $this->error('This expense has already been voided.', 400);
        }

        DB::transaction(function () use ($expense) {
            $bank = $expense->bank;

            // Create a reversal bank transaction (deposit to restore the funds)
            $balanceBefore = $bank->balance;
            $balanceAfter = $balanceBefore + $expense->amount;

            BankTransaction::create([
                'reference_number' => BankTransaction::generateReferenceNumber(),
                'bank_id' => $bank->id,
                'type' => BankTransaction::TYPE_DEPOSIT,
                'amount' => $expense->amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => 'Voided Expense Reversal: '.$expense->reference_number,
                'payee' => 'System Reversal',
                'transaction_date' => now()->toDateString(),
                'created_by' => auth()->id(),
            ]);

            $bank->update(['balance' => $balanceAfter]);

            // Mark expense as voided
            $expense->update(['status' => Expense::STATUS_VOIDED]);
        });

        $expense->load(['category', 'store', 'bank', 'createdBy']);

        return $this->success([
            'expense' => new ExpenseResource($expense),
            'bank_new_balance' => $expense->bank->fresh()->balance,
        ], 'Expense voided and funds reversed successfully.');
    }

    /**
     * Attach (or replace) a receipt photo on an expense.
     */
    public function uploadReceipt(Request $request, Expense $expense, ReceiptStorage $storage): JsonResponse
    {
        $request->validate([
            'receipt' => ReceiptStorage::VALIDATION_RULE,
        ]);

        $oldPath = $expense->receipt_photo;
        $newPath = $storage->store($request->file('receipt'), ReceiptStorage::DIR_EXPENSE_RECEIPTS);

        $expense->forceFill(['receipt_photo' => $newPath])->save();
        $storage->delete($oldPath);

        $expense->load(['category', 'store', 'bank', 'createdBy']);

        return $this->success([
            'expense' => new ExpenseResource($expense),
        ], $oldPath ? 'Receipt replaced.' : 'Receipt attached.');
    }

    /**
     * Clear an expense's receipt photo.
     */
    public function deleteReceipt(Request $request, Expense $expense, ReceiptStorage $storage): JsonResponse
    {
        if ($expense->receipt_photo === null) {
            $expense->load(['category', 'store', 'bank', 'createdBy']);

            return $this->success([
                'expense' => new ExpenseResource($expense),
            ], 'No receipt to remove.');
        }

        $storage->delete($expense->receipt_photo);
        $expense->forceFill(['receipt_photo' => null])->save();

        $expense->load(['category', 'store', 'bank', 'createdBy']);

        return $this->success([
            'expense' => new ExpenseResource($expense),
        ], 'Receipt removed.');
    }

    /**
     * Get all expense categories.
     */
    public function categories(): JsonResponse
    {
        $categories = ExpenseCategory::active()
            ->withCount('expenses')
            ->orderBy('name')
            ->get();

        return $this->success([
            'categories' => ExpenseCategoryResource::collection($categories),
        ]);
    }

    /**
     * Get expense summary/dashboard.
     */
    public function summary(Request $request): JsonResponse
    {
        $period = $request->input('period', 'this_month');
        [$startDate, $endDate] = $this->getDateRange($period);

        // Total expenses for the period
        $periodExpenses = Expense::active()
            ->whereBetween('expense_date', [$startDate, $endDate]);

        $totalAmount = (clone $periodExpenses)->sum('amount');
        $expenseCount = (clone $periodExpenses)->count();

        // Expenses by category
        $byCategory = Expense::active()
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->selectRaw('expense_category_id, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('expense_category_id')
            ->with('category')
            ->get()
            ->map(fn ($item) => [
                'category_id' => $item->expense_category_id,
                'category_name' => $item->category?->name ?? 'Uncategorized',
                'total' => (float) $item->total,
                'count' => (int) $item->count,
            ]);

        // Expenses by store/branch
        $byStore = Expense::active()
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->selectRaw('store_id, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('store_id')
            ->with('store')
            ->get()
            ->map(fn ($item) => [
                'store_id' => $item->store_id,
                'store_name' => $item->store?->name ?? 'All Stores',
                'total' => (float) $item->total,
                'count' => (int) $item->count,
            ]);

        // Recent expenses
        $recentExpenses = Expense::with(['category', 'store', 'bank', 'createdBy'])
            ->active()
            ->latest('expense_date')
            ->latest('id')
            ->limit(10)
            ->get();

        return $this->success([
            'period' => $period,
            'date_range' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'summary' => [
                'total_amount' => $totalAmount,
                'formatted_total' => number_format($totalAmount, 2),
                'expense_count' => $expenseCount,
            ],
            'by_category' => $byCategory,
            'by_store' => $byStore,
            'recent_expenses' => ExpenseResource::collection($recentExpenses),
        ]);
    }

    /**
     * Get date range based on period string.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function getDateRange(string $period): array
    {
        return match ($period) {
            'today' => [
                Carbon::today()->startOfDay(),
                Carbon::today()->endOfDay(),
            ],
            'yesterday' => [
                Carbon::yesterday()->startOfDay(),
                Carbon::yesterday()->endOfDay(),
            ],
            'this_week' => [
                Carbon::now()->startOfWeek(),
                Carbon::now()->endOfDay(),
            ],
            'last_week' => [
                Carbon::now()->subWeek()->startOfWeek(),
                Carbon::now()->subWeek()->endOfWeek(),
            ],
            'this_month' => [
                Carbon::now()->startOfMonth(),
                Carbon::now()->endOfDay(),
            ],
            'last_month' => [
                Carbon::now()->subMonth()->startOfMonth(),
                Carbon::now()->subMonth()->endOfMonth(),
            ],
            'this_year' => [
                Carbon::now()->startOfYear(),
                Carbon::now()->endOfDay(),
            ],
            default => [
                Carbon::now()->startOfMonth(),
                Carbon::now()->endOfDay(),
            ],
        };
    }
}
