<?php

namespace App\Http\Controllers\API\v1\customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\LoginRequest;
use App\Http\Requests\Customer\RegisterRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Traits\ApiResponse;
use App\Models\CustomerRelations\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    use ApiResponse;

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        if (! Auth::guard('customer')->attempt($credentials)) {
            return $this->error('Invalid credentials', 401);
        }

        $customer = Auth::guard('customer')->user();

        if (! $customer->status) {
            Auth::guard('customer')->logout();

            return $this->error('Your account has been deactivated', 403);
        }

        $token = $customer->createToken('Customer Access Token')->accessToken;

        return $this->success([
            'customer' => new CustomerResource($customer),
            'token' => $token,
            'token_type' => 'Bearer',
        ], 'Login successful');
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $customer = Customer::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'password' => $request->password,
            'code' => $this->generateCustomerCode(),
            'status' => true,
            'user_id' => 0,
            'points' => 0,
        ]);

        $token = $customer->createToken('Customer Access Token')->accessToken;

        return $this->created([
            'customer' => new CustomerResource($customer),
            'token' => $token,
            'token_type' => 'Bearer',
        ], 'Registration successful');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success(
            new CustomerResource($request->user('customer-api'))
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user('customer-api')->token()->revoke();

        return $this->success(null, 'Logged out successfully');
    }

    protected function generateCustomerCode(): string
    {
        do {
            $code = 'CUST-'.strtoupper(Str::random(8));
        } while (Customer::where('code', $code)->exists());

        return $code;
    }
}
