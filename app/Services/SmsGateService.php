<?php

namespace App\Services;

use App\Contracts\SmsRelayContract;
use App\Models\CustomerRelations\CustomerPhoneOtp;
use App\Models\OutboundSmsLog;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Send + verify SMS OTPs via the open-source SMS Gateway for Android
 * (https://docs.sms-gate.app). Drop-in replacement for VeroSmsService —
 * same return shapes, same OTP-row plumbing — but with:
 *
 *   - REST + JSON request/response
 *   - JWT bearer auth (rotating, cached)
 *   - Stateful message lifecycle: Pending → Processed → Sent → Delivered | Failed
 *   - Caller-supplied message id (idempotency)
 *
 * Endpoints used (relative to base_url, e.g. https://api.sms-gate.app/3rdparty/v1):
 *   POST /auth/token          — Basic → JWT
 *   POST /messages            — send
 *   GET  /messages/{id}       — status
 *
 * Phone format on the wire is E.164 (+639XXXXXXXXX). Storage stays in
 * local 09XXX form so admin search keeps working; we convert at the
 * network boundary only.
 */
class SmsGateService implements SmsRelayContract
{
    public const RESULT_OK = 'ok';

    public const RESULT_COOLDOWN = 'cooldown';

    public const RESULT_HOURLY_CAP = 'hourly_cap';

    public const RESULT_SEND_FAILED = 'send_failed';

    public function send(string $phone, string $message, string $type = 'general', ?string $ipAddress = null): array
    {
        $phone = $this->normalizePhone($phone);
        $cfg = config('services.sms_gate');

        if (empty($cfg['base_url']) || empty($cfg['username'])) {
            OutboundSmsLog::create([
                'phone' => $phone,
                'type' => $type,
                'status' => OutboundSmsLog::STATUS_SENT,
                'message_length' => strlen($message),
                'ip_address' => $ipAddress,
            ]);

            Log::channel(config('logging.default'))
                ->info("SmsGate dev mode — {$type} to {$phone}: ".substr($message, 0, 60).(strlen($message) > 60 ? '…' : ''));

            return ['status' => self::RESULT_OK, 'message' => 'Sent (dev mode).'];
        }

        return $this->dispatchToRelay($phone, $message, $type, $ipAddress);
    }

    public function sendOtp(string $phone, ?string $ipAddress = null): array
    {
        $phone = $this->normalizePhone($phone);
        $otpCfg = config('services.sms.otp');

        if ($cooldownResult = $this->checkCooldown($phone, $otpCfg)) {
            return $cooldownResult;
        }

        if ($capResult = $this->checkHourlyCap($phone, $otpCfg)) {
            return $capResult;
        }

        $code = (string) random_int(100000, 999999);
        $ttlMinutes = (int) ($otpCfg['ttl_minutes'] ?? 10);
        $message = $this->otpMessage($code, $ttlMinutes);

        // Same brute-force-bypass guard the VeroSMS path uses: expire
        // every prior un-consumed OTP for this phone before issuing a
        // new one, so a resend can't slide the attempt counter back
        // to zero.
        CustomerPhoneOtp::where('phone', $phone)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->update(['expires_at' => now()]);

        $cfg = config('services.sms_gate');

        if (empty($cfg['base_url']) || empty($cfg['username'])) {
            CustomerPhoneOtp::create([
                'phone' => $phone,
                'code_hash' => hash('sha256', $code),
                'expires_at' => now()->addMinutes($ttlMinutes),
                'ip_address' => $ipAddress,
                'created_at' => now(),
            ]);

            OutboundSmsLog::create([
                'phone' => $phone,
                'type' => OutboundSmsLog::TYPE_OTP_REGISTER,
                'status' => OutboundSmsLog::STATUS_SENT,
                'message_length' => strlen($message),
                'ip_address' => $ipAddress,
            ]);

            Log::channel(config('logging.default'))
                ->info("SmsGate dev mode — OTP for {$phone}: {$code}");

            return [
                'status' => self::RESULT_OK,
                'message' => 'Code sent (dev mode — check log file).',
                'dev_code' => app()->environment(['local', 'testing']) ? $code : null,
            ];
        }

        $dispatch = $this->dispatchToRelay($phone, $message, OutboundSmsLog::TYPE_OTP_REGISTER, $ipAddress);

        if ($dispatch['status'] !== self::RESULT_OK) {
            // Don't burn the cooldown on a relay failure — no OTP row
            // was written, the customer can retry immediately rather
            // than wait 60s for a code that never went out.
            return $dispatch;
        }

        CustomerPhoneOtp::create([
            'phone' => $phone,
            'code_hash' => hash('sha256', $code),
            'expires_at' => now()->addMinutes($ttlMinutes),
            'ip_address' => $ipAddress,
            'sms_id' => $dispatch['sms_id'] ?? null,
            'created_at' => now(),
        ]);

        return [
            'status' => self::RESULT_OK,
            'message' => 'Code sent. Check your messages.',
            'sms_id' => $dispatch['sms_id'] ?? null,
        ];
    }

    public function verify(string $phone, string $code): bool
    {
        $phone = $this->normalizePhone($phone);
        $code = trim($code);

        // Read-check-increment must be atomic — same TOCTOU guard the
        // VeroSMS path uses. lockForUpdate serialises parallel calls.
        return DB::transaction(function () use ($phone, $code) {
            $otp = CustomerPhoneOtp::where('phone', $phone)
                ->whereNull('consumed_at')
                ->where('expires_at', '>', now())
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if (! $otp) {
                return false;
            }

            $max = (int) (config('services.sms.otp.max_verify_attempts') ?? 5);
            if ($otp->attempts >= $max) {
                return false;
            }

            $otp->increment('attempts');

            if (! hash_equals($otp->code_hash, hash('sha256', $code))) {
                return false;
            }

            $otp->forceFill(['consumed_at' => now()])->save();

            return true;
        });
    }

    public function pollStatus(OutboundSmsLog $log): ?OutboundSmsLog
    {
        if (! $log->sms_id) {
            return null;
        }

        $cfg = config('services.sms_gate');
        if (empty($cfg['base_url'])) {
            return null;
        }

        try {
            $response = $this->client()->get($cfg['base_url'].config('services.sms_gate.messages_path', '/message').'/'.$log->sms_id);
        } catch (\Throwable $e) {
            Log::warning('SmsGate pollStatus failed', ['sms_id' => $log->sms_id, 'error' => $e->getMessage()]);

            return null;
        }

        if (! $response->ok()) {
            return null;
        }

        $body = $response->json() ?? [];
        $state = $body['state'] ?? null;

        if (! $state) {
            return null;
        }

        $log->forceFill([
            'status' => $this->mapState($state),
            'last_checked_at' => now(),
        ])->save();

        return $log->refresh();
    }

    public function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone) ?? '';

        if (str_starts_with($digits, '63') && strlen($digits) === 12) {
            return '0'.substr($digits, 2);
        }
        if (str_starts_with($digits, '09') && strlen($digits) === 11) {
            return $digits;
        }
        if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            return '0'.$digits;
        }

        return $digits;
    }

    // -------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------

    private function dispatchToRelay(string $phone, string $message, string $type, ?string $ipAddress): array
    {
        try {
            $response = $this->client()->post(config('services.sms_gate.base_url').config('services.sms_gate.messages_path', '/message'), $this->buildSendPayload($phone, $message));
        } catch (\Throwable $e) {
            $this->logFailureRow($phone, $type, $message, $ipAddress, $e->getMessage());

            return [
                'status' => self::RESULT_SEND_FAILED,
                'message' => 'Could not send SMS. Please try again.',
                'error' => $e->getMessage(),
            ];
        }

        if (! $response->successful()) {
            $errorMsg = $this->extractError($response);
            $this->logFailureRow($phone, $type, $message, $ipAddress, $errorMsg);

            return [
                'status' => self::RESULT_SEND_FAILED,
                'message' => 'Could not send SMS. Please try again.',
                'error' => $errorMsg,
            ];
        }

        $body = $response->json() ?? [];
        $messageId = $body['id'] ?? null;

        OutboundSmsLog::create([
            'phone' => $phone,
            'type' => $type,
            'sms_id' => $messageId,
            'status' => OutboundSmsLog::STATUS_SENT,
            'message_length' => strlen($message),
            'ip_address' => $ipAddress,
        ]);

        return [
            'status' => self::RESULT_OK,
            'message' => 'Sent.',
            'sms_id' => $messageId,
        ];
    }

    /**
     * Build the request body for the active relay flavor.
     *
     *   local  → {phoneNumbers, message, simNumber, withDeliveryReport, ttl}
     *   cloud  → {phoneNumbers, textMessage: {text}, simNumber, …}
     *
     * Null/empty optionals are dropped — the local Android server NPEs
     * on a null `deviceId` field rather than treating it as absent.
     */
    private function buildSendPayload(string $phone, string $message): array
    {
        $cfg = config('services.sms_gate');
        $shared = [
            'phoneNumbers' => [$this->toE164($phone)],
            'simNumber' => (int) ($cfg['sim'] ?? 1),
            'deviceId' => $cfg['device_id'] ?: null,
            'withDeliveryReport' => true,
            // ttl bounds the relay's retry window so a stale message
            // doesn't dispatch hours later when the device reconnects.
            'ttl' => 600,
        ];

        $messageShape = ($cfg['payload_flavor'] ?? 'local') === 'cloud'
            ? ['textMessage' => ['text' => $message]]
            : ['message' => $message];

        return array_filter(
            array_merge($shared, $messageShape),
            fn ($v) => $v !== null && $v !== ''
        );
    }

    private function logFailureRow(string $phone, string $type, string $message, ?string $ipAddress, string $error): void
    {
        OutboundSmsLog::create([
            'phone' => $phone,
            'type' => $type,
            'status' => OutboundSmsLog::STATUS_FAILED,
            'message_length' => strlen($message),
            'ip_address' => $ipAddress,
            'error' => substr($error, 0, 500),
        ]);

        Log::warning('SmsGate send failed', ['phone' => $phone, 'type' => $type, 'error' => $error]);
    }

    private function checkCooldown(string $phone, array $otpCfg): ?array
    {
        $lastSent = CustomerPhoneOtp::where('phone', $phone)
            ->orderByDesc('created_at')
            ->first();

        if (! $lastSent || ! $lastSent->created_at) {
            return null;
        }

        $cooldown = (int) ($otpCfg['cooldown_seconds'] ?? 60);
        $secondsSince = $lastSent->created_at->diffInSeconds(now());

        if ($secondsSince < $cooldown) {
            return [
                'status' => self::RESULT_COOLDOWN,
                'message' => 'Please wait before requesting another code.',
                'retry_in' => (int) ceil($cooldown - $secondsSince),
            ];
        }

        return null;
    }

    private function checkHourlyCap(string $phone, array $otpCfg): ?array
    {
        $maxPerHour = (int) ($otpCfg['max_send_per_hour'] ?? 5);
        $sentLastHour = CustomerPhoneOtp::where('phone', $phone)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($sentLastHour >= $maxPerHour) {
            return [
                'status' => self::RESULT_HOURLY_CAP,
                'message' => 'Too many codes requested for this number. Try again in an hour.',
            ];
        }

        return null;
    }

    private function otpMessage(string $code, int $ttlMinutes): string
    {
        return "Your APEX verification code is {$code}. It expires in {$ttlMinutes} minutes. Do not share this code.";
    }

    /**
     * The Pending → Processed → Sent → Delivered | Failed lifecycle
     * collapses to our four-state enum:
     *   - Pending / Processed: still queued at the relay → 'sent'
     *   - Sent: carrier accepted → 'processing'
     *   - Delivered: handset confirmed → 'delivered'
     *   - Failed: terminal failure → 'failed'
     */
    private function mapState(string $state): string
    {
        return match (strtolower($state)) {
            'pending', 'processed' => OutboundSmsLog::STATUS_SENT,
            'sent' => OutboundSmsLog::STATUS_PROCESSING,
            'delivered' => OutboundSmsLog::STATUS_DELIVERED,
            'failed' => OutboundSmsLog::STATUS_FAILED,
            default => OutboundSmsLog::STATUS_SENT,
        };
    }

    private function toE164(string $local09): string
    {
        if (str_starts_with($local09, '09') && strlen($local09) === 11) {
            return '+63'.substr($local09, 1);
        }
        if (str_starts_with($local09, '+')) {
            return $local09;
        }

        return '+'.$local09;
    }

    // -------------------------------------------------------------------
    // Auth (cached JWT)
    // -------------------------------------------------------------------

    private function client(): \Illuminate\Http\Client\PendingRequest
    {
        $cfg = config('services.sms_gate');
        $timeout = (int) ($cfg['timeout'] ?? 15);

        // The Android local-server mode doesn't expose /auth/token at
        // all — auth is HTTP Basic on every request, end of story. We
        // skip the mint flow when auth_mode = 'basic' so the local
        // case doesn't waste a round-trip getting a 404 back.
        if (($cfg['auth_mode'] ?? 'basic') === 'jwt') {
            return Http::withToken($this->accessToken())
                ->acceptJson()
                ->asJson()
                ->timeout($timeout);
        }

        return Http::withBasicAuth($cfg['username'], $cfg['password'])
            ->acceptJson()
            ->asJson()
            ->timeout($timeout);
    }

    private function accessToken(): string
    {
        // Cached so we don't burn a /auth/token round-trip per dispatch.
        // 5-minute safety buffer before the token actually expires.
        return Cache::remember('sms_gate:jwt', now()->addSeconds(3300), function () {
            return $this->mintToken();
        });
    }

    private function mintToken(): string
    {
        $cfg = config('services.sms_gate');
        $response = Http::withBasicAuth($cfg['username'], $cfg['password'])
            ->acceptJson()
            ->asJson()
            ->timeout(10)
            ->post($cfg['base_url'].'/auth/token', [
                'scopes' => ['messages:write', 'messages:read'],
                'ttl' => 3600,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('SmsGate auth failed: HTTP '.$response->status().' '.$response->body());
        }

        $token = $response->json('access_token');
        if (! is_string($token) || $token === '') {
            throw new \RuntimeException('SmsGate auth returned no access_token.');
        }

        return $token;
    }

    private function extractError(Response $response): string
    {
        $body = $response->json() ?? [];
        if (isset($body['message']) && is_string($body['message'])) {
            return $body['message'];
        }

        return 'HTTP '.$response->status();
    }
}
