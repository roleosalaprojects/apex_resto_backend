<?php

namespace App\Http\Controllers\API\v1\mobile;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    use ApiResponse;

    /**
     * Register a device token for push notifications.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string|max:500',
            'platform' => 'required|string|in:ios,android',
        ]);

        DeviceToken::updateOrCreate(
            ['token' => $validated['token']],
            [
                'user_id' => $request->user()->id,
                'platform' => $validated['platform'],
            ]
        );

        return $this->success(null, 'Device token registered.');
    }

    /**
     * Unregister a device token.
     */
    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
        ]);

        DeviceToken::where('token', $validated['token'])
            ->where('user_id', $request->user()->id)
            ->delete();

        return $this->success(null, 'Device token unregistered.');
    }
}
