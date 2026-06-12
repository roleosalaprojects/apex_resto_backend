<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\Employees\Role;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmService
{
    /**
     * Send a push notification to a specific user.
     */
    public function sendToUser(int $userId, string $title, string $body, array $data = []): int
    {
        $tokens = DeviceToken::forUser($userId)->pluck('token')->toArray();

        return $this->sendToTokens($tokens, $title, $body, $data);
    }

    /**
     * Send a push notification to multiple users.
     */
    public function sendToUsers(array $userIds, string $title, string $body, array $data = []): int
    {
        $tokens = DeviceToken::whereIn('user_id', $userIds)->pluck('token')->toArray();

        return $this->sendToTokens($tokens, $title, $body, $data);
    }

    /**
     * Send a push notification to all registered devices.
     */
    public function sendToAll(string $title, string $body, array $data = []): int
    {
        $tokens = DeviceToken::pluck('token')->toArray();

        return $this->sendToTokens($tokens, $title, $body, $data);
    }

    /**
     * Send a push notification to users whose role has a specific permission enabled.
     *
     * @param  int  $businessUserId  The business owner's user_id for scoping
     * @param  string  $permission  The role permission column (e.g. 'sls', 'invntry', 'attndnc')
     */
    public function sendToUsersWithPermission(int $businessUserId, string $permission, string $title, string $body, array $data = []): int
    {
        $roleIds = Role::where('user_id', $businessUserId)
            ->where($permission, true)
            ->pluck('id');

        if ($roleIds->isEmpty()) {
            return 0;
        }

        $userIds = User::where('user_id', $businessUserId)
            ->whereIn('role_id', $roleIds)
            ->pluck('id')
            ->toArray();

        return $this->sendToUsers($userIds, $title, $body, $data);
    }

    /**
     * Send push notification to specific FCM tokens.
     *
     * Uses the FCM v1 HTTP API with a service account.
     * Returns the number of successfully sent messages.
     */
    public function sendToTokens(array $tokens, string $title, string $body, array $data = []): int
    {
        if (empty($tokens)) {
            return 0;
        }

        $projectId = config('services.fcm.project_id');
        $credentials = config('services.fcm.credentials');

        if (! $projectId || ! $credentials) {
            Log::warning('FCM not configured — skipping push notification.');

            return 0;
        }

        $accessToken = $this->getAccessToken($credentials);
        if (! $accessToken) {
            return 0;
        }

        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
        $sent = 0;

        foreach ($tokens as $token) {
            try {
                $response = Http::withToken($accessToken)->post($url, [
                    'message' => [
                        'token' => $token,
                        'notification' => [
                            'title' => $title,
                            'body' => $body,
                        ],
                        'data' => array_map('strval', $data),
                    ],
                ]);

                if ($response->successful()) {
                    $sent++;
                } else {
                    $error = $response->json('error.message', 'Unknown error');
                    Log::warning("FCM send failed for token: {$error}");

                    // Remove invalid tokens
                    if ($response->status() === 404 || str_contains($error, 'not a valid FCM registration token')) {
                        DeviceToken::where('token', $token)->delete();
                        Log::info('Removed invalid FCM token.');
                    }
                }
            } catch (\Exception $e) {
                Log::error("FCM send exception: {$e->getMessage()}");
            }
        }

        return $sent;
    }

    /**
     * Get an OAuth2 access token from the service account credentials.
     */
    private function getAccessToken(string $credentialsPath): ?string
    {
        try {
            if (! file_exists($credentialsPath)) {
                Log::error("FCM credentials file not found: {$credentialsPath}");

                return null;
            }

            $credentials = json_decode(file_get_contents($credentialsPath), true);

            // Build JWT
            $now = time();
            $header = base64url_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $payload = base64url_encode(json_encode([
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]));

            $signature = '';
            openssl_sign(
                "{$header}.{$payload}",
                $signature,
                $credentials['private_key'],
                OPENSSL_ALGO_SHA256
            );
            $jwt = "{$header}.{$payload}.".base64url_encode($signature);

            // Exchange JWT for access token
            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if ($response->successful()) {
                return $response->json('access_token');
            }

            Log::error('Failed to get FCM access token: '.$response->body());

            return null;
        } catch (\Exception $e) {
            Log::error("FCM auth error: {$e->getMessage()}");

            return null;
        }
    }
}

/**
 * URL-safe base64 encode.
 */
if (! function_exists('base64url_encode')) {
    function base64url_encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
