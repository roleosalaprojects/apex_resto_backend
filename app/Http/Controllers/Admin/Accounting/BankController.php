<?php

namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Admin\HelperController;
use App\Http\Controllers\Controller;
use App\Http\Requests\Bank\StoreRequest;
use App\Http\Requests\Bank\UpdateRequest;
use App\Http\Requests\BankTransaction\DepositRequest;
use App\Http\Requests\BankTransaction\TransferRequest;
use App\Http\Requests\BankTransaction\WithdrawalRequest;
use App\Models\Accounting\Bank;
use App\Models\Accounting\BankTransaction;
use App\Services\ReceiptStorage;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Exceptions\Exception;

class BankController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Application|Factory|View|\Illuminate\View\View
     */
    public function index()
    {
        return view('admin.accounting.banks.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return JsonResponse
     */
    public function store(StoreRequest $request)
    {
        $validated = $request->validated();
        $bank = Bank::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Bank account '.$bank->account_name.' - '.$bank->account_number.' created successfully',
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @return Application|Factory|\Illuminate\View\View|View
     */
    public function show(Bank $bank)
    {
        $otherBanks = Bank::where('id', '!=', $bank->id)->get();

        return view('admin.accounting.banks.show', compact('bank', 'otherBanks'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return Response
     */
    public function edit(Bank $bank)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @return JsonResponse
     */
    public function update(UpdateRequest $request, Bank $bank)
    {
        $validated = $request->validated();
        $bank->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Bank account '.$bank->account_name.' - '.$bank->account_number.' updated successfully',
        ]);

        return response()->json($request->validated());
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return JsonResponse
     */
    public function destroy(Bank $bank)
    {
        $bank->delete();

        return response()->json([
            'success' => true,
            'message' => 'Bank details successfully deleted',
        ]);
    }

    public function table()
    {
        $helper = new HelperController;
        $query = Bank::query();
        try {
            return DataTables($query)
                ->addColumn('actions', function ($bank) use ($helper) {
                    return $helper->actionButtonsReturnModal($bank, 'banks', 'bank');
                })
                ->rawColumns(['actions'])
                ->make(true);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function getBank(Bank $bank)
    {
        return \response()->json([
            'success' => true,
            'bank' => $bank,
        ]);
    }

    public function getEWallets()
    {
        return response()->json([
            'success' => true,
            'e-wallets' => Bank::where('account_type', '4')->get(),
        ]);
    }

    public function getBankAccounts()
    {
        return response()->json([
            'success' => true,
            'bank-accounts' => Bank::where('account_type', '!=', '4')->get(),
        ]);
    }

    /**
     * Select2 AJAX endpoint. Returns [{id, text}] for any bank that can
     * receive a cashless payment (excludes virtual Cash-on-Hand
     * accounts of account_type = 5).
     */
    public function select(Request $request): JsonResponse
    {
        $term = trim((string) $request->input('term', ''));

        $banks = Bank::query()
            ->where('account_type', '!=', 5)
            ->when($term !== '', function ($q) use ($term) {
                $q->where(function ($inner) use ($term) {
                    $inner->where('account_name', 'like', "%{$term}%")
                        ->orWhere('bank_name', 'like', "%{$term}%");
                });
            })
            ->orderBy('account_name')
            ->take(100)
            ->get(['id', 'account_name', 'bank_name', 'account_number']);

        $data = $banks->map(fn (Bank $bank) => [
            'id' => $bank->id,
            'text' => "{$bank->account_name} ({$bank->bank_name}) {$bank->account_number}",
        ])->all();

        return response()->json($data);
    }

    public function deposit(DepositRequest $request, Bank $bank): JsonResponse
    {
        $validated = $request->validated();

        $transaction = DB::transaction(function () use ($validated, $bank) {
            $balanceBefore = $bank->balance;
            $balanceAfter = $balanceBefore + $validated['amount'];

            $tx = BankTransaction::create([
                'reference_number' => BankTransaction::generateReferenceNumber(),
                'bank_id' => $bank->id,
                'type' => BankTransaction::TYPE_DEPOSIT,
                'amount' => $validated['amount'],
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => $validated['description'] ?? null,
                'payee' => $validated['payee'] ?? null,
                'transaction_date' => $validated['transaction_date'],
                'created_by' => auth()->id(),
            ]);

            $bank->update(['balance' => $balanceAfter]);

            return $tx;
        });

        return response()->json([
            'success' => true,
            'message' => 'Deposit of '.number_format($validated['amount'], 2).' recorded successfully.',
            'new_balance' => $bank->fresh()->balance,
            'transaction_id' => $transaction->id,
        ]);
    }

    public function withdrawal(WithdrawalRequest $request, Bank $bank): JsonResponse
    {
        $validated = $request->validated();

        $transaction = DB::transaction(function () use ($validated, $bank) {
            $balanceBefore = $bank->balance;
            $balanceAfter = $balanceBefore - $validated['amount'];

            $tx = BankTransaction::create([
                'reference_number' => BankTransaction::generateReferenceNumber(),
                'bank_id' => $bank->id,
                'type' => BankTransaction::TYPE_WITHDRAWAL,
                'amount' => $validated['amount'],
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => $validated['description'] ?? null,
                'payee' => $validated['payee'] ?? null,
                'transaction_date' => $validated['transaction_date'],
                'created_by' => auth()->id(),
            ]);

            $bank->update(['balance' => $balanceAfter]);

            return $tx;
        });

        return response()->json([
            'success' => true,
            'message' => 'Withdrawal of '.number_format($validated['amount'], 2).' recorded successfully.',
            'new_balance' => $bank->fresh()->balance,
            'transaction_id' => $transaction->id,
        ]);
    }

    public function transfer(TransferRequest $request, Bank $bank): JsonResponse
    {
        $validated = $request->validated();
        $destinationBank = Bank::findOrFail($validated['transfer_to_bank_id']);

        $sourceTransaction = DB::transaction(function () use ($validated, $bank, $destinationBank) {
            $referenceNumber = BankTransaction::generateReferenceNumber();

            // Source account - Transfer Out
            $sourceBalanceBefore = $bank->balance;
            $sourceBalanceAfter = $sourceBalanceBefore - $validated['amount'];

            $source = BankTransaction::create([
                'reference_number' => $referenceNumber,
                'bank_id' => $bank->id,
                'transfer_to_bank_id' => $destinationBank->id,
                'type' => BankTransaction::TYPE_TRANSFER_OUT,
                'amount' => $validated['amount'],
                'balance_before' => $sourceBalanceBefore,
                'balance_after' => $sourceBalanceAfter,
                'description' => $validated['description'] ?? 'Transfer to '.$destinationBank->account_name,
                'transaction_date' => $validated['transaction_date'],
                'created_by' => auth()->id(),
            ]);

            $bank->update(['balance' => $sourceBalanceAfter]);

            // Destination account - Transfer In
            $destBalanceBefore = $destinationBank->balance;
            $destBalanceAfter = $destBalanceBefore + $validated['amount'];

            BankTransaction::create([
                'reference_number' => $referenceNumber.'-IN',
                'bank_id' => $destinationBank->id,
                'transfer_to_bank_id' => $bank->id,
                'type' => BankTransaction::TYPE_TRANSFER_IN,
                'amount' => $validated['amount'],
                'balance_before' => $destBalanceBefore,
                'balance_after' => $destBalanceAfter,
                'description' => $validated['description'] ?? 'Transfer from '.$bank->account_name,
                'transaction_date' => $validated['transaction_date'],
                'created_by' => auth()->id(),
            ]);

            $destinationBank->update(['balance' => $destBalanceAfter]);

            return $source;
        });

        return response()->json([
            'success' => true,
            'message' => 'Transfer of '.number_format($validated['amount'], 2).' to '.$destinationBank->account_name.' completed successfully.',
            'new_balance' => $bank->fresh()->balance,
            // Proof attaches to the source-account leg of the transfer.
            'transaction_id' => $sourceTransaction->id,
        ]);
    }

    public function transactionsTable(Bank $bank): JsonResponse
    {
        $helper = new HelperController;
        $query = $bank->transactions()->with(['createdBy', 'transferToBank'])->latest('transaction_date')->latest('id');

        try {
            return DataTables($query)
                ->addColumn('type_badge', function ($transaction) {
                    $colors = [
                        BankTransaction::TYPE_DEPOSIT => 'success',
                        BankTransaction::TYPE_WITHDRAWAL => 'danger',
                        BankTransaction::TYPE_TRANSFER_OUT => 'warning',
                        BankTransaction::TYPE_TRANSFER_IN => 'info',
                    ];
                    $color = $colors[$transaction->type] ?? 'secondary';

                    return '<span class="badge bg-'.$color.'">'.$transaction->type_name.'</span>';
                })
                ->addColumn('formatted_amount', function ($transaction) {
                    $prefix = $transaction->isDebit() ? '-' : '+';
                    $color = $transaction->isDebit() ? 'danger' : 'success';

                    return '<span class="text-'.$color.' fw-bold">'.$prefix.number_format($transaction->amount, 2).'</span>';
                })
                ->addColumn('formatted_balance', function ($transaction) {
                    return number_format($transaction->balance_after, 2);
                })
                ->addColumn('formatted_date', function ($transaction) {
                    return $transaction->transaction_date->format('M d, Y');
                })
                ->addColumn('created_by_name', function ($transaction) {
                    return $transaction->createdBy?->name ?? 'System';
                })
                ->addColumn('payee_display', function ($transaction) {
                    if ($transaction->payee) {
                        return $transaction->payee;
                    }
                    if ($transaction->transferToBank) {
                        $prefix = $transaction->type == BankTransaction::TYPE_TRANSFER_IN ? 'From: ' : 'To: ';

                        return '<span class="text-muted">'.$prefix.$transaction->transferToBank->account_name.'</span>';
                    }

                    return '<span class="text-muted">-</span>';
                })
                ->addColumn('proof', function ($transaction) {
                    if ($transaction->proof_photo) {
                        $url = e($transaction->proof_photo_url);

                        return '<a href="'.$url.'" target="_blank" rel="noopener" title="View slip">'
                            .'<img src="'.$url.'" alt="Proof" style="max-height:32px;max-width:48px;border-radius:4px;object-fit:cover;">'
                            .'</a>';
                    }

                    return '<button type="button" class="btn btn-sm btn-light-primary upload-proof-btn" data-transaction-id="'.$transaction->id.'">Upload</button>';
                })
                ->rawColumns(['type_badge', 'formatted_amount', 'payee_display', 'proof'])
                ->make(true);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Upload (or replace) a deposit slip / transfer screenshot on a bank transaction.
     */
    public function uploadTransactionProof(Request $request, BankTransaction $transaction, ReceiptStorage $storage): JsonResponse
    {
        $request->validate([
            'proof' => ReceiptStorage::VALIDATION_RULE,
        ]);

        $oldPath = $transaction->proof_photo;
        $newPath = $storage->store($request->file('proof'), ReceiptStorage::DIR_BANK_PROOFS);

        $transaction->forceFill(['proof_photo' => $newPath])->save();
        $storage->delete($oldPath);

        return response()->json([
            'success' => true,
            'message' => $oldPath ? 'Proof replaced.' : 'Proof attached.',
            'proof_photo' => $transaction->proof_photo,
            'proof_photo_url' => $transaction->proof_photo_url,
        ]);
    }

    /**
     * Remove the proof photo from a bank transaction.
     */
    public function deleteTransactionProof(BankTransaction $transaction, ReceiptStorage $storage): JsonResponse
    {
        if ($transaction->proof_photo === null) {
            return response()->json(['success' => true, 'message' => 'No proof to remove.']);
        }

        $storage->delete($transaction->proof_photo);
        $transaction->forceFill(['proof_photo' => null])->save();

        return response()->json(['success' => true, 'message' => 'Proof removed.']);
    }
}
