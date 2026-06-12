<?php

namespace Tests\Feature\Services;

use App\Contracts\SmsRelayContract;
use App\Models\OutboundSmsLog;
use App\Services\SmsGateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * SmsGateService — the sms-gate.app implementation of SmsRelayContract.
 * Uses Http::fake() so we never touch the real relay; assertions cover
 * the request shape, the JWT mint+cache, the state-mapping table, and
 * the dev-mode fallback when base_url / username aren't configured.
 */
class SmsGateServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Point config at the sms_gate driver with valid auth + base
        // URL so we exercise the live-mode branches.
        config([
            'services.sms.driver' => 'sms_gate',
            'services.sms_gate.base_url' => 'https://api.sms-gate.app/3rdparty/v1',
            // Tests exercise the cloud-style plural path + JWT auth
            // because that's the path the swagger spec documents. The
            // local Android server defaults to /message (singular) +
            // Basic auth — both shapes are supported by the service.
            'services.sms_gate.messages_path' => '/messages',
            'services.sms_gate.payload_flavor' => 'cloud',
            'services.sms_gate.auth_mode' => 'jwt',
            'services.sms_gate.username' => 'user',
            'services.sms_gate.password' => 'pass',
            'services.sms_gate.device_id' => 'DEV-1',
            'services.sms_gate.sim' => 1,
        ]);

        Cache::forget('sms_gate:jwt');
    }

    public function test_contract_binding_resolves_to_sms_gate_when_driver_flag_is_sms_gate(): void
    {
        $bound = app(SmsRelayContract::class);
        $this->assertInstanceOf(SmsGateService::class, $bound);
    }

    public function test_send_mints_jwt_then_posts_to_messages_endpoint(): void
    {
        Http::fake([
            '*/auth/token' => Http::response(['access_token' => 'jwt-abc', 'expires_at' => now()->addHour()->toIso8601String()], 200),
            '*/messages' => Http::response(['id' => '01H8WAM0000', 'state' => 'Pending'], 202),
        ]);

        $result = app(SmsGateService::class)
            ->send('09171234567', 'hello', 'general', '127.0.0.1');

        $this->assertSame(SmsGateService::RESULT_OK, $result['status']);
        $this->assertSame('01H8WAM0000', $result['sms_id']);

        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/auth/token')
                && $request->hasHeader('Authorization')
                && str_starts_with($request->header('Authorization')[0], 'Basic ');
        });
        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/messages')
                && $request->hasHeader('Authorization', 'Bearer jwt-abc')
                && $request['phoneNumbers'][0] === '+639171234567'
                && $request['textMessage']['text'] === 'hello'
                && $request['simNumber'] === 1
                && $request['withDeliveryReport'] === true;
        });

        $this->assertDatabaseHas('outbound_sms_logs', [
            'phone' => '09171234567',
            'sms_id' => '01H8WAM0000',
            'status' => OutboundSmsLog::STATUS_SENT,
        ]);
    }

    public function test_jwt_is_cached_so_second_send_reuses_token(): void
    {
        Http::fake([
            '*/auth/token' => Http::response(['access_token' => 'jwt-cached'], 200),
            '*/messages' => Http::response(['id' => '01H8WAM0001', 'state' => 'Pending'], 202),
        ]);

        $svc = app(SmsGateService::class);
        $svc->send('09171234567', 'first', 'general');
        $svc->send('09171234567', 'second', 'general');

        $authCalls = collect(Http::recorded())
            ->filter(fn ($pair) => str_ends_with($pair[0]->url(), '/auth/token'))
            ->count();

        $this->assertSame(1, $authCalls, 'JWT must be cached — only one /auth/token round-trip per cache window.');
    }

    public function test_send_failure_logs_failed_row_and_returns_error(): void
    {
        Http::fake([
            '*/auth/token' => Http::response(['access_token' => 'jwt-x'], 200),
            '*/messages' => Http::response(['message' => 'device offline'], 502),
        ]);

        $result = app(SmsGateService::class)
            ->send('09171234567', 'will fail', 'general');

        $this->assertSame(SmsGateService::RESULT_SEND_FAILED, $result['status']);
        $this->assertDatabaseHas('outbound_sms_logs', [
            'phone' => '09171234567',
            'status' => OutboundSmsLog::STATUS_FAILED,
            'error' => 'device offline',
        ]);
    }

    public function test_poll_status_maps_lifecycle_states_to_local_enum(): void
    {
        $log = OutboundSmsLog::create([
            'phone' => '09171234567',
            'type' => 'general',
            'sms_id' => '01H8WAM0002',
            'status' => OutboundSmsLog::STATUS_SENT,
            'message_length' => 10,
        ]);

        $cases = [
            'Pending' => OutboundSmsLog::STATUS_SENT,
            'Processed' => OutboundSmsLog::STATUS_SENT,
            'Sent' => OutboundSmsLog::STATUS_PROCESSING,
            'Delivered' => OutboundSmsLog::STATUS_DELIVERED,
            'Failed' => OutboundSmsLog::STATUS_FAILED,
        ];

        // One fake with a response sequence — each pollStatus() call
        // pops the next stub. Cleaner than re-faking per iteration,
        // which can leak state between cycles.
        $sequence = Http::sequence();
        foreach (array_keys($cases) as $remote) {
            $sequence->push(['id' => $log->sms_id, 'state' => $remote]);
        }
        Http::fake([
            '*/auth/token' => Http::response(['access_token' => 'jwt-poll'], 200),
            '*/messages/'.$log->sms_id => $sequence,
        ]);

        foreach ($cases as $remote => $expectedLocal) {
            $updated = app(SmsGateService::class)->pollStatus($log);

            $this->assertNotNull($updated, "Remote state {$remote} should resolve.");
            $this->assertSame($expectedLocal, $updated->status,
                "Remote state {$remote} should map to local '{$expectedLocal}'.");
        }
    }

    public function test_dev_mode_short_circuits_when_base_url_is_empty(): void
    {
        config(['services.sms_gate.base_url' => '', 'services.sms_gate.username' => '']);

        Http::fake();

        $result = app(SmsGateService::class)
            ->send('09171234567', 'dev mode', 'general');

        $this->assertSame(SmsGateService::RESULT_OK, $result['status']);
        Http::assertNothingSent();
        $this->assertDatabaseHas('outbound_sms_logs', [
            'phone' => '09171234567',
            'type' => 'general',
            'status' => OutboundSmsLog::STATUS_SENT,
        ]);
    }

    public function test_send_otp_dev_mode_returns_dev_code_in_local_or_testing(): void
    {
        config(['services.sms_gate.base_url' => '']);

        $result = app(SmsGateService::class)->sendOtp('09171234567', '127.0.0.1');

        $this->assertSame(SmsGateService::RESULT_OK, $result['status']);
        $this->assertIsString($result['dev_code'] ?? null);
        $this->assertSame(6, strlen($result['dev_code']));
    }

    public function test_normalize_phone_canonicalizes_three_input_formats(): void
    {
        $svc = app(SmsGateService::class);

        $this->assertSame('09171234567', $svc->normalizePhone('09171234567'));
        $this->assertSame('09171234567', $svc->normalizePhone('+639171234567'));
        $this->assertSame('09171234567', $svc->normalizePhone('639171234567'));
        $this->assertSame('09171234567', $svc->normalizePhone('9171234567'));
    }

    public function test_phone_is_e164_at_the_wire(): void
    {
        // Storage stays in local 09… form; the +63… conversion only
        // happens at the network boundary. Lock that down.
        Http::fake([
            '*/auth/token' => Http::response(['access_token' => 'jwt-w'], 200),
            '*/messages' => Http::response(['id' => '01H8WAM0003', 'state' => 'Pending'], 202),
        ]);

        app(SmsGateService::class)->send('09171234567', 'test', 'general');

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/messages')
            && $r['phoneNumbers'][0] === '+639171234567');

        $this->assertDatabaseHas('outbound_sms_logs', ['phone' => '09171234567']);
    }
}
