<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\OutboundSmsLog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Webhook endpoint for the SMS Gateway for Android relay.
 *
 * Payload shape (per https://docs.sms-gate.app/features/webhooks/):
 *   {
 *     "deviceId":  "...",
 *     "event":     "sms:sent" | "sms:delivered" | "sms:failed" | ...,
 *     "id":        "...",
 *     "payload":   { "messageId": "...", "recipient": "...", ... },
 *     "webhookId": "..."
 *   }
 *
 * Signing input: raw_body_bytes + X-Timestamp_value, HMAC-SHA256.
 * Headers:
 *   X-Signature  — hex HMAC-SHA256 of the signing input
 *   X-Timestamp  — Unix seconds; we reject anything > 5min out of band
 *
 * Why we read the raw body (not the parsed JSON) for HMAC: re-encoding
 * the parsed array can shift whitespace and key order; the relay
 * computed the signature against the literal bytes. `$request->
 * getContent()` is the only safe input.
 */
class SmsGateWebhookController extends Controller
{
    /** Replay protection window. */
    private const TIMESTAMP_TOLERANCE_SECONDS = 300;

    public function handle(Request $request): Response
    {
        $cfg = config('services.sms_gate');

        // 1. IP allowlist (cheapest reject, runs first). If the env
        // var is unset, this layer is bypassed — useful for dev where
        // the relay device may be on DHCP.
        if (! $this->ipAllowed($request, $cfg)) {
            Log::warning('SmsGate webhook rejected: IP not allowed', [
                'ip' => $request->ip(),
            ]);

            return response('IP not allowed.', 401);
        }

        // 2. HTTP Basic auth (also cheap). Configured by embedding
        // credentials in the webhook URL — `http://user:pass@host/...`
        // — which the relay's HTTP client converts to an Authorization
        // header for us. Skipped when both env vars are empty.
        if (! $this->basicAuthValid($request, $cfg)) {
            Log::warning('SmsGate webhook rejected: bad basic auth', [
                'ip' => $request->ip(),
            ]);

            return response('Bad credentials.', 401);
        }

        // 3. HMAC + timestamp (the cryptographic guarantee that the
        // relay sent this exact body). Always on; fail-closed if the
        // signing key is missing.
        $signingKey = (string) ($cfg['webhook_signing_key'] ?? '');
        $signature = (string) $request->header('X-Signature', '');
        $timestamp = (string) $request->header('X-Timestamp', '');
        $rawBody = $request->getContent();

        if ($signingKey === '' || $signature === '' || $timestamp === '') {
            return response('Missing webhook signing material.', 401);
        }

        // 3a. Replay window. Reject anything > 5 min out of band so a
        // captured payload can't be re-played later.
        $timestampInt = is_numeric($timestamp) ? (int) $timestamp : 0;
        if ($timestampInt <= 0 || abs(time() - $timestampInt) > self::TIMESTAMP_TOLERANCE_SECONDS) {
            return response('Stale timestamp.', 401);
        }

        // 3b. Signature. hash_equals is constant-time to dodge timing attacks.
        $expected = hash_hmac('sha256', $rawBody.$timestamp, $signingKey);
        if (! hash_equals($expected, $signature)) {
            Log::warning('SmsGate webhook signature mismatch', [
                'ip' => $request->ip(),
                'event' => $request->input('event'),
            ]);

            return response('Bad signature.', 401);
        }

        // 4. Find the row to update. messageId is the SmsGate id we
        // already stamped into outbound_sms_logs.sms_id at send time.
        $event = (string) $request->input('event', '');
        $messageId = (string) $request->input('payload.messageId', '');

        if ($event === '' || $messageId === '') {
            return response('Missing event or payload.messageId.', 422);
        }

        $log = OutboundSmsLog::where('sms_id', $messageId)->first();
        if (! $log) {
            // Unknown row: return 200 so the relay doesn't retry forever
            // (the message id may belong to a different environment).
            return response()->noContent();
        }

        $this->applyEvent($log, $event, $request->input('payload', []));

        return response()->noContent();
    }

    /**
     * Whether the request IP is in the allowlist. An unset / empty
     * `webhook_allowed_ips` means "no allowlist, accept everything".
     * The value is a comma-separated list of exact IPs (CIDR ranges
     * aren't supported here — keep it simple for the local-LAN case).
     */
    private function ipAllowed(Request $request, array $cfg): bool
    {
        $raw = trim((string) ($cfg['webhook_allowed_ips'] ?? ''));
        if ($raw === '') {
            return true;
        }

        $allowed = array_values(array_filter(array_map('trim', explode(',', $raw))));

        return in_array((string) $request->ip(), $allowed, true);
    }

    /**
     * Constant-time check against optional Basic auth credentials. If
     * neither user nor password env var is set, this layer is off and
     * every request passes through to HMAC.
     */
    private function basicAuthValid(Request $request, array $cfg): bool
    {
        $expectedUser = (string) ($cfg['webhook_basic_user'] ?? '');
        $expectedPass = (string) ($cfg['webhook_basic_password'] ?? '');

        if ($expectedUser === '' && $expectedPass === '') {
            return true;
        }

        return hash_equals($expectedUser, (string) $request->getUser())
            && hash_equals($expectedPass, (string) $request->getPassword());
    }

    private function applyEvent(OutboundSmsLog $log, string $event, array $payload): void
    {
        // Never downgrade from a terminal state — webhooks can fire
        // out of order (sms:delivered arriving before sms:sent) and
        // we don't want a delivered message to flip back to processing.
        if (in_array($log->status, [OutboundSmsLog::STATUS_DELIVERED, OutboundSmsLog::STATUS_FAILED], true)) {
            return;
        }

        $newStatus = match ($event) {
            'sms:sent' => OutboundSmsLog::STATUS_PROCESSING,
            'sms:delivered' => OutboundSmsLog::STATUS_DELIVERED,
            'sms:failed' => OutboundSmsLog::STATUS_FAILED,
            default => null,
        };

        if ($newStatus === null) {
            return;
        }

        $update = [
            'status' => $newStatus,
            'last_checked_at' => now(),
        ];

        if ($event === 'sms:failed' && isset($payload['reason'])) {
            $update['error'] = substr((string) $payload['reason'], 0, 500);
        }

        $log->forceFill($update)->save();
    }
}
