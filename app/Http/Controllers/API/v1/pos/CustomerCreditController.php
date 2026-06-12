<?php

namespace App\Http\Controllers\API\v1\pos;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Http\Traits\ApiResponse;
use App\Models\CustomerRelations\Customer;
use App\Models\CustomerRelations\CustomerCreditTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CustomerCreditController extends Controller
{
    use ApiResponse;

    public function balance(Customer $customer): JsonResponse
    {
        $transactions = CustomerCreditTransaction::where('customer_id', $customer->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return $this->success([
            'customer' => new CustomerResource($customer),
            'transactions' => $transactions,
        ]);
    }

    public function payment(Request $request, Customer $customer): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,e-wallet,bank_transfer,cheque',
            'bank_id' => 'nullable|integer|exists:banks,id',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
            'pos_id' => 'required|integer|exists:pos,id',
            'store_id' => 'required|integer|exists:stores,id',
        ]);

        // Require bank_id for e-wallet and bank_transfer
        if (in_array($validated['payment_method'], ['e-wallet', 'bank_transfer'])) {
            if (empty($validated['bank_id'])) {
                return $this->error('Please select a bank/e-wallet account', 422);
            }
            if (empty($validated['reference_number'])) {
                return $this->error('Reference number is required', 422);
            }
        }

        // Require reference_number for cheque
        if ($validated['payment_method'] === 'cheque' && empty($validated['reference_number'])) {
            return $this->error('Cheque number is required', 422);
        }

        if ($customer->credit_balance <= 0) {
            return $this->error('Customer has no outstanding balance', 422);
        }

        if ($validated['amount'] > $customer->credit_balance) {
            return $this->error('Payment amount exceeds outstanding balance', 422);
        }

        $transaction = DB::transaction(function () use ($validated, $customer) {
            $customer->lockForUpdate();
            $customer->refresh();

            $newBalance = $customer->credit_balance - $validated['amount'];
            $customer->update(['credit_balance' => $newBalance]);

            return CustomerCreditTransaction::create([
                'customer_id' => $customer->id,
                'type' => 'payment',
                'amount' => $validated['amount'],
                'balance_after' => $newBalance,
                'payment_method' => $validated['payment_method'],
                'bank_id' => $validated['bank_id'] ?? null,
                'reference_number' => $validated['reference_number'] ?? null,
                'reference_type' => 'payment',
                'notes' => $validated['notes'] ?? null,
                'pos_id' => $validated['pos_id'],
                'store_id' => $validated['store_id'],
                'user_id' => Auth::guard('api')->id(),
            ]);
        });

        $customer->refresh();

        return $this->success([
            'transaction' => $transaction,
            'customer' => new CustomerResource($customer),
        ], 'Payment recorded successfully');
    }
}
