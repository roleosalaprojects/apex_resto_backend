<?php

namespace App\Http\Controllers\API\v1\mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\v1\pos\Customer\StoreRequest;
use App\Http\Resources\CustomerDetailResource;
use App\Http\Resources\CustomerResource;
use App\Http\Traits\ApiResponse;
use App\Models\CustomerRelations\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $customers = Customer::where('status', true)
            ->where(function ($query) use ($request) {
                $query->where('name', 'like', '%'.$request->term.'%');
                $query->orWhere('email', 'like', '%'.$request->term.'%');
                $query->orWhere('phone', 'like', '%'.$request->term.'%');
                $query->orWhere('code', 'like', '%'.$request->term.'%');
            })
            ->get();

        return $this->success($customers, null, 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['status'] = true;
        $validated['points'] = 0.0001;
        $customer = Customer::create($validated);

        return $this->success(new CustomerResource($customer), message: 'Customer registered successfully.');
    }

    /**
     * Display the specified resource with purchase history and loyalty points.
     */
    public function show(Customer $customer): JsonResponse
    {
        $customer->load([
            'purchases' => function ($query) {
                $query->with(['lines.item', 'store', 'sold_by'])
                    ->where('cancelled', false)
                    ->orderBy('created_at', 'desc')
                    ->limit(50);
            },
            'creditTransactions' => function ($query) {
                $query->with(['store', 'user'])
                    ->orderBy('created_at', 'desc')
                    ->limit(50);
            },
        ]);

        $transactionSummary = $this->calculateTransactionSummary($customer);

        $creditSummary = [
            'credit_limit' => (float) ($customer->credit_limit ?? 0),
            'credit_balance' => (float) ($customer->credit_balance ?? 0),
            'available_credit' => (float) ($customer->available_credit ?? 0),
            'credit_term_days' => (int) ($customer->credit_term_days ?? 0),
        ];

        $creditTransactions = $customer->creditTransactions->map(function ($t) {
            return [
                'id' => $t->id,
                'type' => $t->type,
                'amount' => (float) $t->amount,
                'balance_after' => (float) $t->balance_after,
                'due_date' => $t->due_date?->format('Y-m-d'),
                'payment_method' => $t->payment_method,
                'reference_number' => $t->reference_number,
                'notes' => $t->notes,
                'store' => $t->store?->name,
                'user' => $t->user?->name,
                'created_at' => $t->created_at->toIso8601String(),
            ];
        });

        $resource = (new CustomerDetailResource($customer))
            ->setTransactionSummary($transactionSummary);

        $data = $resource->resolve();
        $data['credit_summary'] = $creditSummary;
        $data['credit_transactions'] = $creditTransactions;

        return $this->success($data);
    }

    /**
     * Calculate transaction summary for the customer.
     *
     * @return array{total_transactions: int, total_spent: float, average_transaction: float, last_purchase_date: string|null}
     */
    private function calculateTransactionSummary(Customer $customer): array
    {
        $purchases = $customer->purchases()
            ->where('cancelled', false)
            ->selectRaw('COUNT(*) as total_count, SUM(total) as total_amount, MAX(created_at) as last_purchase')
            ->first();

        $totalTransactions = (int) ($purchases->total_count ?? 0);
        $totalSpent = (float) ($purchases->total_amount ?? 0);
        $averageTransaction = $totalTransactions > 0 ? $totalSpent / $totalTransactions : 0;

        return [
            'total_transactions' => $totalTransactions,
            'total_spent' => round($totalSpent, 2),
            'average_transaction' => round($averageTransaction, 2),
            'last_purchase_date' => $purchases->last_purchase,
        ];
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Customer $customer)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Customer $customer)
    {
        //
    }
}
