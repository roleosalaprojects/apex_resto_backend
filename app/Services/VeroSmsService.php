<?php

namespace App\Services;

use App\Contracts\SmsRelayContract;
use App\Models\CustomerRelations\CustomerPhoneOtp;
use App\Models\OutboundSmsLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Send + verify 6-digit SMS OTPs via the VeroSMS Android relay.
 *
 * VeroSMS endpoints (per the integration guide):
 *   GET /api/send/sms?device_id=...&sim=sim1&number=...&sms=...
 *      Authorization: Bearer <api_key>
 *      → 200 { status: true, sms_id: N }
 *
 * We generate the OTP server-side and pass the full message body to
 * VeroSMS — the relay doesn't generate codes for us. Codes are stored
 * hashed (sha256) so a DB dump doesn't leak active OTPs.
 *
 * Dev mode: if `services.verosms.base_url` is empty (typical for
 * local dev where there's no Android device on the LAN), the OTP is
 * logged to laravel.log under the `verosms` channel instead of being
 * sent. The verification flow is untouched, so QA can still grab the
 * code from the log file.
 */
class VeroSmsService implements SmsRelayContract
{
    /**
     * Result returned by sendOtp(). Callers should NOT log or display
     * `code` to the customer — it's exposed only for dev-mode UX where
     * we want to surface the OTP in a session flash so QA doesn't dig
     * through the log.
     */
    public const RESULT_OK = 'ok';

    public const RESULT_COOLDOWN = 'cooldown';

    public const RESULT_HOURLY_CAP = 'hourly_cap';

    public const RESULT_SEND_FAILED = 'send_failed';

    /**
     * Send an arbitrary SMS to $phone. Doesn't generate an OTP — the
     * caller supplies the full message. Used both internally by
     * sendOtp() and externally for non-OTP traffic (admin broadcasts,
     * tests against new templates, debug pokes).
     *
     * Writes one outbound_sms_logs row per attempt with the given
     * `$type` so the admin SMS log can distinguish OTPs from anything
     * else. We do NOT persist the message body — message_length is the
     * only signal we keep for accounting.
     *
     * @return array{status: string, message: string, sms_id?: int, error?: string}
     */
    public function send(string $phone, string $message, string $type = 'general', ?string $ipAddress = null): array
    {
        $phone = $this->normalizePhone($phone);
        $cfg = config('services.verosms');

        if (empty($cfg['base_url'])) {
            OutboundSmsLog::create([
                'phone' => $phone,
                'type' => $type,
                'status' => OutboundSmsLog::STATUS_SENT,
                'message_length' => strlen($message),
                'ip_address' => $ipAddress,
            ]);

            Log::channel(config('logging.default'))
                ->info("VeroSMS dev mode — {$type} to {$phone}: ".substr($message, 0, 60).(strlen($message) > 60 ? '…' : ''));

            return [
                'status' => self::RESULT_OK,
                'message' => 'Sent (dev mode).',
            ];
        }

        try {
            $response = Http::withToken($cfg['api_key'])
                ->timeout((int) ($cfg['timeout'] ?? 15))
                ->get($this->sendUrl($cfg['base_url']), [
                    'device_id' => $cfg['device_id'],
                    'sim' => $cfg['sim'] ?? 'sim1',
                    'number' => $phone,
                    'sms' => $message,
                ]);

            $body = $response->json() ?? [];

            if (! $response->ok() || ($body['status'] ?? false) !== true) {
                Log::warning('VeroSMS send failed', [
                    'phone' => $phone,
                    'type' => $type,
                    'http_status' => $response->status(),
                    'body' => $body,
                ]);

                OutboundSmsLog::create([
                    'phone' => $phone,
                    'type' => $type,
                    'status' => OutboundSmsLog::STATUS_FAILED,
                    'message_length' => strlen($message),
                    'ip_address' => $ipAddress,
                    'error' => substr((string) ($body['message'] ?? 'HTTP '.$response->status()), 0, 500),
                ]);

                return [
                    'status' => self::RESULT_SEND_FAILED,
                    'message' => $body['message'] ?? 'Could not send SMS. Please try again.',
                    'error' => (string) ($body['message'] ?? 'HTTP '.$response->status()),
                ];
            }

            OutboundSmsLog::create([
                'phone' => $phone,
                'type' => $type,
                'sms_id' => ! empty($body['sms_id']) ? (int) $body['sms_id'] : null,
                'status' => OutboundSmsLog::STATUS_SENT,
                'message_length' => strlen($message),
                'ip_address' => $ipAddress,
            ]);

            return [
                'status' => self::RESULT_OK,
                'message' => 'Sent.',
                'sms_id' => $body['sms_id'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::error('VeroSMS exception', [
                'phone' => $phone,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            OutboundSmsLog::create([
                'phone' => $phone,
                'type' => $type,
                'status' => OutboundSmsLog::STATUS_FAILED,
                'message_length' => strlen($message),
                'ip_address' => $ipAddress,
                'error' => substr($e->getMessage(), 0, 500),
            ]);

            return [
                'status' => self::RESULT_SEND_FAILED,
                'message' => 'Could not send SMS. Please try again.',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Issue a fresh OTP for $phone and (try to) send it via VeroSMS.
     *
     * @return array{
     *     status: string,
     *     message: string,
     *     dev_code?: string,
     *     retry_in?: int,
     *     sms_id?: int
     * }
     */
    public function sendOtp(string $phone, ?string $ipAddress = null): array
    {
        $phone = $this->normalizePhone($phone);
        $cfg = config('services.verosms');

        // Per-phone cooldown — typically 60s to dampen spam without
        // making the customer wait too long if they fat-fingered the
        // input.
        $lastSent = CustomerPhoneOtp::where('phone', $phone)
            ->orderByDesc('created_at')
            ->first();

        if ($lastSent && $lastSent->created_at) {
            $secondsSince = $lastSent->created_at->diffInSeconds(now());
            $cooldown = (int) ($cfg['otp_send_cooldown_seconds'] ?? 60);
            if ($secondsSince < $cooldown) {
                return [
                    'status' => self::RESULT_COOLDOWN,
                    'message' => 'Please wait before requesting another code.',
                    'retry_in' => (int) ceil($cooldown - $secondsSince),
                ];
            }
        }

        // Hourly cap — guards against a determined attacker churning
        // through codes from one phone.
        $sentLastHour = CustomerPhoneOtp::where('phone', $phone)
            ->where('created_at', '>=', now()->subHour())
            ->count();
        $maxPerHour = (int) ($cfg['otp_max_send_per_hour'] ?? 5);
        if ($sentLastHour >= $maxPerHour) {
            return [
                'status' => self::RESULT_HOURLY_CAP,
                'message' => 'Too many codes requested for this number. Try again in an hour.',
            ];
        }

        $code = (string) random_int(100000, 999999);
        $ttlMinutes = (int) ($cfg['otp_ttl_minutes'] ?? 10);
        $message = $this->messageFor($code, $ttlMinutes);

        // Invalidate every prior un-consumed OTP for this phone before
        // writing the new one. Without this, an attacker who burned
        // through the 5 verify attempts on OTP-1 could just resend and
        // get a fresh OTP-2 with attempts=0 — sliding the lockout
        // counter back to zero. Expiring the predecessor closes that
        // hole (verify() filters expires_at > now()).
        CustomerPhoneOtp::where('phone', $phone)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->update(['expires_at' => now()]);

        if (empty($cfg['base_url'])) {
            // Dev mode — no SMS relay configured. Log it and
            // (carefully) surface the code to the caller so the dev
            // UX can show it. Store the OTP row so the customer can
            // verify against it.
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
                ->info("VeroSMS dev mode — OTP for {$phone}: {$code}");

            return [
                'status' => self::RESULT_OK,
                'message' => 'Code sent (dev mode — check log file).',
                // Only echo the code back when the app is genuinely a
                // non-prod build. A misconfigured prod (env not loaded,
                // base_url accidentally blank) would otherwise leak
                // every code to the JSON response.
                'dev_code' => app()->environment(['local', 'testing']) ? $code : null,
            ];
        }

        try {
            $response = Http::withToken($cfg['api_key'])
                ->timeout((int) ($cfg['timeout'] ?? 15))
                ->get($this->sendUrl($cfg['base_url']), [
                    'device_id' => $cfg['device_id'],
                    'sim' => $cfg['sim'] ?? 'sim1',
                    'number' => $phone,
                    'sms' => $message,
                ]);

            $body = $response->json() ?? [];
            if (! $response->ok() || ($body['status'] ?? false) !== true) {
                Log::warning('VeroSMS send failed', [
                    'phone' => $phone,
                    'http_status' => $response->status(),
                    'body' => $body,
                ]);

                // Forensic row even on failure — so an admin can see
                // "we tried to text X at 14:02 and the relay refused".
                OutboundSmsLog::create([
                    'phone' => $phone,
                    'type' => 'otp_register',
                    'status' => OutboundSmsLog::STATUS_FAILED,
                    'message_length' => strlen($message),
                    'ip_address' => $ipAddress,
                    'error' => substr((string) ($body['message'] ?? 'HTTP '.$response->status()), 0, 500),
                ]);

                // Don't burn the cooldown — no OTP row gets written,
                // so the customer can retry immediately rather than
                // wait 60s for a code that never went out.
                return [
                    'status' => self::RESULT_SEND_FAILED,
                    'message' => $body['message'] ?? 'Could not send code. Please try again.',
                ];
            }

            // Persist only after the relay confirmed acceptance.
            CustomerPhoneOtp::create([
                'phone' => $phone,
                'code_hash' => hash('sha256', $code),
                'expires_at' => now()->addMinutes($ttlMinutes),
                'ip_address' => $ipAddress,
                'sms_id' => ! empty($body['sms_id']) ? (int) $body['sms_id'] : null,
                'created_at' => now(),
            ]);

            OutboundSmsLog::create([
                'phone' => $phone,
                'type' => 'otp_register',
                'sms_id' => ! empty($body['sms_id']) ? (int) $body['sms_id'] : null,
                'status' => OutboundSmsLog::STATUS_SENT,
                'message_length' => strlen($message),
                'ip_address' => $ipAddress,
            ]);

            return [
                'status' => self::RESULT_OK,
                'message' => 'Code sent.',
                'sms_id' => $body['sms_id'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::error('VeroSMS exception', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);

            OutboundSmsLog::create([
                'phone' => $phone,
                'type' => 'otp_register',
                'status' => OutboundSmsLog::STATUS_FAILED,
                'message_length' => strlen($message),
                'ip_address' => $ipAddress,
                'error' => substr($e->getMessage(), 0, 500),
            ]);

            return [
                'status' => self::RESULT_SEND_FAILED,
                'message' => 'Could not send code. Please try again.',
            ];
        }
    }

    /**
     * Re-poll VeroSMS for the live delivery state of a previously
     * dispatched SMS and update the matching outbound_sms_logs row.
     * Returns the refreshed row (or null if the relay can't find the
     * sms_id — could be a typo in the row's stored id, could be the
     * relay reset its database).
     */
    public function pollStatus(OutboundSmsLog $log): ?OutboundSmsLog
    {
        if (! $log->sms_id) {
            return null;
        }

        $cfg = config('services.verosms');
        if (empty($cfg['base_url'])) {
            return null;
        }

        try {
            $response = Http::withToken($cfg['api_key'])
                ->timeout((int) ($cfg['timeout'] ?? 15))
                ->get($this->statusUrl($cfg['base_url']), ['id' => $log->sms_id]);

            $body = $response->json() ?? [];
            if (! $response->ok() || ($body['status'] ?? false) !== true) {
                return null;
            }

            $code = isset($body['data']['status']) ? (int) $body['data']['status'] : null;
            $label = OutboundSmsLog::labelFromVeroCode($code);

            $log->forceFill([
                'vero_status_code' => $code,
                'status' => $label ?? $log->status,
                'last_checked_at' => now(),
            ])->save();

            return $log->fresh();
        } catch (\Throwable $e) {
            Log::warning('VeroSMS pollStatus exception', [
                'log_id' => $log->id,
                'sms_id' => $log->sms_id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Build the absolute send-sms URL from whatever shape the operator
     * dropped into VEROSMS_BASE_URL. Accepts all of these:
     *
     *   https://sms.example.com
     *   https://sms.example.com/
     *   https://sms.example.com/api
     *   https://sms.example.com/api/
     *   https://sms.example.com/api/send/sms       (the full URL itself)
     *
     * and always lands on `.../api/send/sms`. Tolerant on purpose —
     * the docs and the admin panel disagree on what to paste.
     */
    private function sendUrl(string $baseUrl): string
    {
        return $this->apiUrl($baseUrl, '/api/send/sms');
    }

    private function statusUrl(string $baseUrl): string
    {
        return $this->apiUrl($baseUrl, '/api/check/status');
    }

    /**
     * Shared base-URL normaliser used by sendUrl + statusUrl. Tolerant
     * of operators pasting the URL with /api/ already on the end (the
     * admin panel often shows it that way).
     */
    private function apiUrl(string $baseUrl, string $path): string
    {
        $url = rtrim($baseUrl, '/');

        if (str_ends_with($url, $path)) {
            return $url;
        }

        // Trim a trailing /api if present so we don't end up hitting
        // .../api/api/<path>.
        if (str_ends_with($url, '/api')) {
            $url = substr($url, 0, -4);
        }

        return $url.$path;
    }

    /**
     * Check $code against the most recent un-consumed, un-expired OTP
     * for $phone. On success, the row is marked consumed so a second
     * attempt with the same code is rejected.
     *
     * Increments `attempts` on every call so we can lock out brute
     * force at otp_max_verify_attempts.
     */
    public function verify(string $phone, string $code): bool
    {
        $phone = $this->normalizePhone($phone);
        $code = trim($code);

        // Read-check-increment must be atomic, otherwise N parallel
        // verify() calls can all see attempts < max simultaneously and
        // each get a guess in (TOCTOU race) — sliding the brute-force
        // lockout from "5 guesses total" to "5 guesses per parallel
        // burst". lockForUpdate serialises them on the row.
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

            $max = (int) (config('services.verosms.otp_max_verify_attempts') ?? 5);
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

    /**
     * Normalize PH numbers to local 09XXXXXXXXX form.
     *
     *   +639171234567   → 09171234567
     *    639171234567   → 09171234567
     *    09171234567    → 09171234567 (unchanged)
     *
     * Anything that doesn't match a PH-like pattern is returned with
     * non-digits stripped, so callers can still trace logs. Validation
     * of the final shape belongs to the FormRequest, not here.
     */
    public function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);
        if ($digits === null) {
            return '';
        }

        // 63 (PH country code) prefix → 09
        if (str_starts_with($digits, '63') && strlen($digits) === 12) {
            return '0'.substr($digits, 2);
        }

        return $digits;
    }

    private function messageFor(string $code, int $ttlMinutes): string
    {
        return "Your verification code is {$code}. It will expire in {$ttlMinutes} minutes. Do not share this code.";
    }
}
