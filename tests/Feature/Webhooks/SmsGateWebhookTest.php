<?php

namespace Tests\Feature\Webhooks;

use App\Models\OutboundSmsLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Webhook receiver for the SMS Gate relay. Tests the signature
 * verification (timestamp + HMAC), the replay window, and the
 * event → OutboundSmsLog.status mapping including the don't-downgrade
 * guard that prevents out-of-order callbacks from regressing state.
 */
class SmsGateWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SIGNING_KEY = 'test-signing-key-do-not-use-in-prod';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.sms_gate.webhook_signing_key' => self::SIGNING_KEY]);
    }

    /**
     * Build the exact JSON the relay sends, with matching signature
     * headers. Mirrors the public payload contract:
     * https://docs.sms-gate.app/features/webhooks/
     */
    private function signedRequest(string $event, string $messageId, array $extra = [], ?int $timestamp = null): array
    {
        $body = json_encode([
            'deviceId' => 'DEV-1',
            'event' => $event,
            'id' => 'whbk-'.$messageId,
            'payload' => array_merge([
                'messageId' => $messageId,
                'sender' => '+639171234567',
                'recipient' => '+639180000000',
                'simNumber' => 1,
            ], $extra),
            'webhookId' => 'wh-1',
        ], JSON_UNESCAPED_SLASHES);

        $ts = (string) ($timestamp ?? time());
        $sig = hash_hmac('sha256', $body.$ts, self::SIGNING_KEY);

        return [
            'body' => $body,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Signature' => $sig,
                'X-Timestamp' => $ts,
            ],
        ];
    }

    private function postWebhook(array $signed): \Illuminate\Testing\TestResponse
    {
        // Symfony's server-params array distinguishes between regular
        // request headers (HTTP_-prefixed) and the CGI specials
        // CONTENT_TYPE / CONTENT_LENGTH (no prefix). Prefixing
        // Content-Type breaks Laravel's JSON parser, which gives a
        // 422 instead of the 204 we actually want to test.
        $server = [];
        foreach ($signed['headers'] as $k => $v) {
            $upper = strtoupper(str_replace('-', '_', $k));
            $server[in_array($upper, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true) ? $upper : 'HTTP_'.$upper] = $v;
        }

        return $this->call(
            'POST',
            route('webhooks.sms-gate'),
            [],
            [],
            [],
            $server,
            $signed['body'],
        );
    }

    public function test_sms_delivered_flips_log_to_delivered(): void
    {
        $log = OutboundSmsLog::create([
            'phone' => '09180000000',
            'type' => 'general',
            'sms_id' => 'msg-1',
            'status' => OutboundSmsLog::STATUS_SENT,
            'message_length' => 20,
        ]);

        $this->postWebhook($this->signedRequest('sms:delivered', 'msg-1', [
            'deliveredAt' => '2026-06-09T10:45:00+08:00',
        ]))->assertNoContent();

        $this->assertSame(OutboundSmsLog::STATUS_DELIVERED, $log->fresh()->status);
    }

    public function test_sms_sent_advances_to_processing(): void
    {
        $log = OutboundSmsLog::create([
            'phone' => '09180000000',
            'type' => 'general',
            'sms_id' => 'msg-2',
            'status' => OutboundSmsLog::STATUS_SENT,
            'message_length' => 20,
        ]);

        $this->postWebhook($this->signedRequest('sms:sent', 'msg-2'))
            ->assertNoContent();

        $this->assertSame(OutboundSmsLog::STATUS_PROCESSING, $log->fresh()->status);
    }

    public function test_sms_failed_records_reason_in_error_column(): void
    {
        $log = OutboundSmsLog::create([
            'phone' => '09180000000',
            'type' => 'general',
            'sms_id' => 'msg-3',
            'status' => OutboundSmsLog::STATUS_SENT,
            'message_length' => 20,
        ]);

        $this->postWebhook($this->signedRequest('sms:failed', 'msg-3', [
            'reason' => 'Network error',
        ]))->assertNoContent();

        $fresh = $log->fresh();
        $this->assertSame(OutboundSmsLog::STATUS_FAILED, $fresh->status);
        $this->assertSame('Network error', $fresh->error);
    }

    public function test_bad_signature_is_rejected(): void
    {
        $log = OutboundSmsLog::create([
            'phone' => '09180000000',
            'type' => 'general',
            'sms_id' => 'msg-4',
            'status' => OutboundSmsLog::STATUS_SENT,
            'message_length' => 20,
        ]);

        $signed = $this->signedRequest('sms:delivered', 'msg-4');
        $signed['headers']['X-Signature'] = str_repeat('0', 64); // wrong hash

        $this->postWebhook($signed)
            ->assertStatus(401);

        $this->assertSame(OutboundSmsLog::STATUS_SENT, $log->fresh()->status,
            'A bad-signature payload must not mutate any log row.');
    }

    public function test_stale_timestamp_is_rejected(): void
    {
        $log = OutboundSmsLog::create([
            'phone' => '09180000000',
            'type' => 'general',
            'sms_id' => 'msg-5',
            'status' => OutboundSmsLog::STATUS_SENT,
            'message_length' => 20,
        ]);

        // 6 minutes in the past — beyond the 5-min replay window.
        $signed = $this->signedRequest('sms:delivered', 'msg-5', [], time() - 360);

        $this->postWebhook($signed)
            ->assertStatus(401);

        $this->assertSame(OutboundSmsLog::STATUS_SENT, $log->fresh()->status);
    }

    public function test_terminal_delivered_is_not_downgraded_by_late_sms_sent(): void
    {
        // Webhook callbacks can fire out of order — the network may
        // deliver sms:delivered before sms:sent. Hard requirement:
        // a delivered row never regresses to processing.
        $log = OutboundSmsLog::create([
            'phone' => '09180000000',
            'type' => 'general',
            'sms_id' => 'msg-6',
            'status' => OutboundSmsLog::STATUS_DELIVERED,
            'message_length' => 20,
        ]);

        $this->postWebhook($this->signedRequest('sms:sent', 'msg-6'))
            ->assertNoContent();

        $this->assertSame(OutboundSmsLog::STATUS_DELIVERED, $log->fresh()->status,
            'sms:sent arriving after sms:delivered must NOT downgrade the row.');
    }

    public function test_unknown_message_id_returns_204_silently(): void
    {
        // Relay should not retry for-rows-we-do-not-know-about; 204
        // tells it to stop.
        $this->postWebhook($this->signedRequest('sms:delivered', 'msg-does-not-exist'))
            ->assertNoContent();
    }

    public function test_endpoint_is_csrf_exempt(): void
    {
        // Implicit assertion: a POST without a CSRF token should not
        // 419 out. Our signature guard would 401 it on its own, but
        // we want to confirm CSRF doesn't fire first.
        $this->postWebhook($this->signedRequest('sms:delivered', 'whatever'))
            ->assertNoContent();
    }

    public function test_missing_signing_key_config_rejects_all_requests(): void
    {
        // Fail-closed: if the operator forgets SMS_GATE_WEBHOOK_SIGNING_KEY,
        // the endpoint refuses every request instead of trusting them.
        config(['services.sms_gate.webhook_signing_key' => '']);

        $this->postWebhook($this->signedRequest('sms:delivered', 'msg-7'))
            ->assertStatus(401);
    }

    // ---------------------------------------------------------------
    // Defense-in-depth: IP allowlist + Basic auth (both opt-in)
    // ---------------------------------------------------------------

    public function test_ip_allowlist_blocks_request_from_unexpected_address(): void
    {
        // The phone-side IP is 192.168.0.30; we only allow that one.
        // Test client comes in as 127.0.0.1 so it gets rejected
        // BEFORE the HMAC check even runs.
        config(['services.sms_gate.webhook_allowed_ips' => '192.168.0.30']);

        $log = OutboundSmsLog::create([
            'phone' => '09180000000',
            'type' => 'general',
            'sms_id' => 'msg-ip-1',
            'status' => OutboundSmsLog::STATUS_SENT,
            'message_length' => 20,
        ]);

        $this->postWebhook($this->signedRequest('sms:delivered', 'msg-ip-1'))
            ->assertStatus(401);

        $this->assertSame(OutboundSmsLog::STATUS_SENT, $log->fresh()->status,
            'IP-blocked requests must not mutate any row.');
    }

    public function test_ip_allowlist_accepts_request_from_listed_address(): void
    {
        // 127.0.0.1 IS allowed, so the request passes through to the
        // HMAC check (which we satisfy with a valid signature).
        config(['services.sms_gate.webhook_allowed_ips' => '127.0.0.1,192.168.0.30']);

        $log = OutboundSmsLog::create([
            'phone' => '09180000000',
            'type' => 'general',
            'sms_id' => 'msg-ip-2',
            'status' => OutboundSmsLog::STATUS_SENT,
            'message_length' => 20,
        ]);

        $this->postWebhook($this->signedRequest('sms:delivered', 'msg-ip-2'))
            ->assertNoContent();

        $this->assertSame(OutboundSmsLog::STATUS_DELIVERED, $log->fresh()->status);
    }

    public function test_ip_allowlist_unset_falls_through_to_hmac(): void
    {
        // Empty / unset = no allowlist applied; behavior should match
        // the original happy-path test.
        config(['services.sms_gate.webhook_allowed_ips' => null]);

        $log = OutboundSmsLog::create([
            'phone' => '09180000000',
            'type' => 'general',
            'sms_id' => 'msg-ip-3',
            'status' => OutboundSmsLog::STATUS_SENT,
            'message_length' => 20,
        ]);

        $this->postWebhook($this->signedRequest('sms:delivered', 'msg-ip-3'))
            ->assertNoContent();

        $this->assertSame(OutboundSmsLog::STATUS_DELIVERED, $log->fresh()->status);
    }

    public function test_basic_auth_required_when_configured(): void
    {
        // When the env vars are set, missing Basic auth is rejected
        // even with a valid HMAC signature.
        config([
            'services.sms_gate.webhook_basic_user' => 'apex-webhook',
            'services.sms_gate.webhook_basic_password' => 'super-secret',
        ]);

        $log = OutboundSmsLog::create([
            'phone' => '09180000000',
            'type' => 'general',
            'sms_id' => 'msg-ba-1',
            'status' => OutboundSmsLog::STATUS_SENT,
            'message_length' => 20,
        ]);

        $this->postWebhook($this->signedRequest('sms:delivered', 'msg-ba-1'))
            ->assertStatus(401);

        $this->assertSame(OutboundSmsLog::STATUS_SENT, $log->fresh()->status,
            'Basic-auth-required reject must not mutate any row.');
    }

    public function test_basic_auth_with_correct_credentials_passes(): void
    {
        config([
            'services.sms_gate.webhook_basic_user' => 'apex-webhook',
            'services.sms_gate.webhook_basic_password' => 'super-secret',
        ]);

        $log = OutboundSmsLog::create([
            'phone' => '09180000000',
            'type' => 'general',
            'sms_id' => 'msg-ba-2',
            'status' => OutboundSmsLog::STATUS_SENT,
            'message_length' => 20,
        ]);

        $signed = $this->signedRequest('sms:delivered', 'msg-ba-2');
        $signed['headers']['Authorization'] = 'Basic '.base64_encode('apex-webhook:super-secret');

        $this->postWebhook($signed)
            ->assertNoContent();

        $this->assertSame(OutboundSmsLog::STATUS_DELIVERED, $log->fresh()->status);
    }

    public function test_basic_auth_with_wrong_password_is_rejected(): void
    {
        config([
            'services.sms_gate.webhook_basic_user' => 'apex-webhook',
            'services.sms_gate.webhook_basic_password' => 'super-secret',
        ]);

        $log = OutboundSmsLog::create([
            'phone' => '09180000000',
            'type' => 'general',
            'sms_id' => 'msg-ba-3',
            'status' => OutboundSmsLog::STATUS_SENT,
            'message_length' => 20,
        ]);

        $signed = $this->signedRequest('sms:delivered', 'msg-ba-3');
        $signed['headers']['Authorization'] = 'Basic '.base64_encode('apex-webhook:wrong-password');

        $this->postWebhook($signed)
            ->assertStatus(401);

        $this->assertSame(OutboundSmsLog::STATUS_SENT, $log->fresh()->status);
    }

    public function test_basic_auth_unset_skips_the_layer(): void
    {
        // Both env vars empty = layer off; even a request with no
        // Authorization header should pass through to HMAC.
        config([
            'services.sms_gate.webhook_basic_user' => null,
            'services.sms_gate.webhook_basic_password' => null,
        ]);

        $log = OutboundSmsLog::create([
            'phone' => '09180000000',
            'type' => 'general',
            'sms_id' => 'msg-ba-4',
            'status' => OutboundSmsLog::STATUS_SENT,
            'message_length' => 20,
        ]);

        $this->postWebhook($this->signedRequest('sms:delivered', 'msg-ba-4'))
            ->assertNoContent();

        $this->assertSame(OutboundSmsLog::STATUS_DELIVERED, $log->fresh()->status);
    }

    public function test_layer_ordering_ip_rejects_before_basic_auth_or_hmac_check(): void
    {
        // Stack all three: bad IP, valid Basic, valid HMAC. We expect
        // 401 from the IP layer with no Log::warning about basic auth
        // or signature — proving the cheap reject runs first.
        config([
            'services.sms_gate.webhook_allowed_ips' => '192.168.0.30',
            'services.sms_gate.webhook_basic_user' => 'u',
            'services.sms_gate.webhook_basic_password' => 'p',
        ]);

        $log = OutboundSmsLog::create([
            'phone' => '09180000000',
            'type' => 'general',
            'sms_id' => 'msg-order-1',
            'status' => OutboundSmsLog::STATUS_SENT,
            'message_length' => 20,
        ]);

        $signed = $this->signedRequest('sms:delivered', 'msg-order-1');
        $signed['headers']['Authorization'] = 'Basic '.base64_encode('u:p');

        $this->postWebhook($signed)
            ->assertStatus(401)
            ->assertSee('IP not allowed');

        $this->assertSame(OutboundSmsLog::STATUS_SENT, $log->fresh()->status);
    }
}
