<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Login user and create token
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors(),
            ], 422);
        }

        // Attempt to authenticate
        if (! Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'error' => 'Invalid credentials',
                'message' => 'The provided credentials are incorrect.',
            ], 401);
        }

        $user = Auth::user();

        // Create Passport token
        $token = $user->createToken('product-image-updater')->accessToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'token_type' => 'Bearer',
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ], 200);
    }

    /**
     * Get authenticated user
     */
    public function user(Request $request)
    {
        return response()->json([
            'success' => true,
            'user' => $request->user(),
        ]);
    }

    /**
     * Logout user (revoke current token)
     */
    public function logout(Request $request)
    {
        // Revoke the Passport token used for the current request
        $request->user()->token()->revoke();

        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out',
        ]);
    }

    /**
     * Logout from all devices (revoke all tokens)
     */
    public function logoutAll(Request $request)
    {
        // Revoke all Passport tokens for the user
        $request->user()->tokens->each(function ($token) {
            $token->revoke();
        });

        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out from all devices',
        ]);
    }
}
