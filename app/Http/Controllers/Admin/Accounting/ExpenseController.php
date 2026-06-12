<?php

namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Admin\HelperController;
use App\Http\Controllers\Controller;
use App\Http\Requests\Expense\StoreRequest;
use App\Http\Requests\Expense\UpdateRequest;
use App\Models\Accounting\Bank;
use App\Models\Accounting\BankTransaction;
use App\Models\Accounting\Expense;
use App\Models\Accounting\ExpenseCategory;
use App\Models\Settings\Store;
use App\Services\ReceiptStorage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Yajra\DataTables\Exceptions\Exception;

class ExpenseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $categories = ExpenseCategory::active()->orderBy('name')->get();
        $stores = Store::where('status', 1)->orderBy('name')->get();
        $banks = Bank::orderBy('bank_name')->get();

        return view('admin.accounting.expenses.index', compact('categories', 'stores', 'banks'));
    }

    /**
     * Store a newly created resource in storage.
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

        return response()->json([
            'success' => true,
            'message' => 'Expense of '.number_format($validated['amount'], 2).' recorded successfully.',
            'expense' => $expense->load(['category', 'store', 'bank', 'createdBy']),
        ]);
    }

    /**
     * Display the specified resource.
     *
     * Content-negotiates: JSON for AJAX callers (preserves backward
     * compatibility), Blade view for direct browser navigation (so the
     * "view details" eye-icon in the DataTable actions column actually
     * renders a readable page instead of dumping raw JSON).
     */
    public function show(Expense $expense): JsonResponse|View
    {
        $expense->load([
            'category',
            'store',
            'bank',
            'bankTransaction',
            'supplier',
            'createdBy',
            'approvedBy',
        ]);

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'expense' => $expense,
            ]);
        }

        return view('admin.accounting.expenses.show', compact('expense'));
    }

    /**
     * Update the specified resource in storage.
     * Note: Only non-financial fields can be updated.
     */
    public function update(UpdateRequest $request, Expense $expense): JsonResponse
    {
        if ($expense->isVoided()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update a voided expense.',
            ], 400);
        }

        $validated = $request->validated();
        $expense->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Expense updated successfully.',
            'expense' => $expense->fresh()->load(['category', 'store', 'bank', 'createdBy']),
        ]);
    }

    /**
     * Void the expense (soft delete with status change).
     * This will also reverse the bank transaction.
     */
    public function destroy(Expense $expense): JsonResponse
    {
        if ($expense->isVoided()) {
            return response()->json([
                'success' => false,
                'message' => 'This expense has already been voided.',
            ], 400);
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

        return response()->json([
            'success' => true,
            'message' => 'Expense voided and funds reversed successfully.',
        ]);
    }

    /**
     * Get expense for editing.
     */
    public function getExpense(Expense $expense): JsonResponse
    {
        $expense->load(['category', 'store', 'bank', 'createdBy']);

        return response()->json([
            'success' => true,
            'expense' => $expense,
        ]);
    }

    /**
     * Upload (or replace) a receipt photo on an expense.
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

        return response()->json([
            'success' => true,
            'message' => $oldPath ? 'Receipt replaced.' : 'Receipt attached.',
            'receipt_photo' => $expense->receipt_photo,
            'receipt_photo_url' => $expense->receipt_photo_url,
        ]);
    }

    /**
     * Remove the receipt photo from an expense.
     */
    public function deleteReceipt(Expense $expense, ReceiptStorage $storage): JsonResponse
    {
        if ($expense->receipt_photo === null) {
            return response()->json(['success' => true, 'message' => 'No receipt to remove.']);
        }

        $storage->delete($expense->receipt_photo);
        $expense->forceFill(['receipt_photo' => null])->save();

        return response()->json(['success' => true, 'message' => 'Receipt removed.']);
    }

    /**
     * DataTable source.
     */
    public function table(): JsonResponse
    {
        $helper = new HelperController;
        $query = Expense::with(['category', 'store', 'bank', 'createdBy'])
            ->latest('expense_date')
            ->latest('id');

        try {
            return DataTables($query)
                ->addColumn('category_name', function ($expense) {
                    return $expense->category?->name ?? '<span class="text-muted">Uncategorized</span>';
                })
                ->addColumn('store_name', function ($expense) {
                    return $expense->store?->name ?? '<span class="text-muted">All Stores</span>';
                })
                ->addColumn('bank_name', function ($expense) {
                    return $expense->bank?->account_name.' - '.$expense->bank?->bank_name;
                })
                ->addColumn('formatted_amount', function ($expense) {
                    return '<span class="text-danger fw-bold">'.number_format($expense->amount, 2).'</span>';
                })
                ->addColumn('formatted_date', function ($expense) {
                    return $expense->expense_date->format('M d, Y');
                })
                ->addColumn('status_badge', function ($expense) {
                    $color = $expense->isActive() ? 'success' : 'danger';

                    return '<span class="badge bg-'.$color.'">'.$expense->status_name.'</span>';
                })
                ->addColumn('created_by_name', function ($expense) {
                    return $expense->createdBy?->name ?? 'System';
                })
                ->addColumn('actions', function ($expense) use ($helper) {
                    if ($expense->isVoided()) {
                        return '<span class="text-muted">Voided</span>';
                    }

                    return $helper->actionButtonsReturnModal($expense, 'expenses', 'expense');
                })
                ->addColumn('receipt', function ($expense) {
                    if ($expense->receipt_photo) {
                        $url = e($expense->receipt_photo_url);

                        return '<a href="'.$url.'" target="_blank" rel="noopener" data-receipt-id="'.$expense->id.'" title="View receipt">'
                            .'<img src="'.$url.'" alt="Receipt" style="max-height:32px;max-width:48px;border-radius:4px;object-fit:cover;">'
                            .'</a>';
                    }

                    return '<button type="button" class="btn btn-sm btn-light-primary upload-receipt-btn" data-expense-id="'.$expense->id.'">Upload</button>';
                })
                ->rawColumns(['category_name', 'store_name', 'formatted_amount', 'status_badge', 'actions', 'receipt'])
                ->make(true);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function export(): StreamedResponse
    {
        $query = Expense::with(['category', 'store', 'bank', 'createdBy'])
            ->latest('expense_date')
            ->latest('id');

        $filename = 'expenses_'.now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Reference #', 'Date', 'Payee', 'Category', 'Store', 'Bank', 'Amount', 'Status', 'Created By']);

            foreach ($query->lazy() as $expense) {
                fputcsv($handle, [
                    $expense->reference_number,
                    $expense->expense_date->format('M d, Y'),
                    $expense->payee,
                    $expense->category?->name ?? 'Uncategorized',
                    $expense->store?->name ?? 'All Stores',
                    trim($expense->bank?->account_name.' - '.$expense->bank?->bank_name, ' -'),
                    number_format($expense->amount, 2, '.', ''),
                    $expense->status_name,
                    $expense->createdBy?->name ?? 'System',
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
