<?php

namespace App\Http\Controllers\API\v1\mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\v1\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Http\Traits\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    use ApiResponse;

    public function login(LoginRequest $request): JsonResponse
    {
        if (Auth::attempt($request->only('email', 'password'))) {
            $user = User::where('id', Auth::user()->id)->with(['details', 'role'])->first();

            return $this->success([
                'token' => $user->createToken('mobileAppToken')->accessToken,
                'user' => new UserResource($user),
            ]);
        }

        return $this->unauthorized('User credentials not found!');
    }

    public function getUser(): JsonResponse
    {
        $user = User::where('id', Auth::user()->id)->with(['details', 'role'])->first();

        return $this->success([
            'token' => $user->createToken('mobileAppToken')->accessToken,
            'user' => new UserResource($user),
        ]);
    }

    public function logout(): JsonResponse
    {
        if (Auth::user()) {
            Auth::user()->token()->revoke();

            return $this->success(null, 'Logout successful');
        }

        return $this->error('Unable to logout');
    }
}
