<?php

namespace App\Contracts;

use App\Models\OutboundSmsLog;

/**
 * One implementation per outbound-SMS provider. The current set:
 *
 *   - App\Services\VeroSmsService  (legacy bespoke relay; failing on prod)
 *   - App\Services\SmsGateService  (sms-gate.app, open source replacement)
 *
 * Pick the active one with `config('services.sms.driver')`, bound in
 * AppServiceProvider. Call sites depend on this interface so swapping
 * providers is a one-line config flip with a roll-back path.
 *
 * Return-shape conventions are inherited from VeroSmsService since
 * every caller already speaks that vocabulary.
 */
interface SmsRelayContract
{
    /**
     * Generic outbound dispatch (non-OTP). Writes one `outbound_sms_logs`
     * row regardless of relay outcome.
     *
     * @return array{status: string, message: string, sms_id?: string, error?: string}
     */
    public function send(string $phone, string $message, string $type = 'general', ?string $ipAddress = null): array;

    /**
     * Issue an OTP for `$phone` and dispatch via the relay. Reuses the
     * `customer_phone_otps` table for cooldown/lockout state across
     * providers, so swapping the relay doesn't reset rate limits.
     *
     * @return array{
     *     status: string,
     *     message: string,
     *     dev_code?: ?string,
     *     retry_in?: int,
     *     sms_id?: string
     * }
     */
    public function sendOtp(string $phone, ?string $ipAddress = null): array;

    /**
     * Verify a 6-digit code against the most recent un-consumed OTP
     * for `$phone`. Atomic; safe under parallel requests.
     */
    public function verify(string $phone, string $code): bool;

    /**
     * Refresh delivery state for one already-dispatched log row.
     * Returns the updated model on success, null on relay error.
     * Webhook-driven providers (SMS Gate) can no-op when the row is
     * already terminal.
     */
    public function pollStatus(OutboundSmsLog $log): ?OutboundSmsLog;

    /**
     * Canonical 09XXXXXXXXX form. Accepts +63… / 63… / 09… variants.
     * Storage stays in the local form so admin search keeps working;
     * implementations convert at the network boundary as needed.
     */
    public function normalizePhone(string $phone): string;
}
