<?php

namespace App\Http\Controllers\API\v1\pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\v1\pos\Customer\StoreRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Traits\ApiResponse;
use App\Models\CustomerRelations\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    use ApiResponse;

    public function customers(): JsonResponse
    {
        $customers = Customer::where('status', true)
            ->select('id', 'name', 'code', 'accumulated_points')
            ->get();

        return $this->success(CustomerResource::collection($customers));
    }

    public function details(Customer $customer): JsonResponse
    {
        return $this->success(new CustomerResource($customer));
    }

    public function searchCustomers(Request $request): JsonResponse
    {
        $term = $request->term;
        $customers = Customer::where('status', true)
            ->where(function ($query) use ($term) {
                $query->where('name', 'LIKE', '%'.$term.'%');
                $query->orWhere('code', $term);
            })
            ->limit(100)
            ->get();

        return $this->success([
            'customers' => CustomerResource::collection($customers),
        ]);
    }

    public function store(StoreRequest $request)
    {
        $validated = $request->validated();
        $validated['status'] = true;
        $validated['points'] = 0.0001;
        $customer = Customer::create($validated);

        return $this->success(new CustomerResource($customer), message: 'Customer registered successfully.');
    }
}
