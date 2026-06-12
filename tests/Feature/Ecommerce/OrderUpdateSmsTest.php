<?php

namespace Tests\Feature\Ecommerce;

use App\Models\CustomerRelations\Customer;
use App\Models\Ecommerce\EcommerceOrder;
use App\Models\OutboundSmsLog;
use App\Models\SmsTemplate;
use Database\Seeders\SmsTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Order status SMS notifications. Tests cover every invariant in the
 * design: notifiable vs silent statuses, per-row idempotency, opt-out,
 * verified-phone gating, disabled / missing templates, and that the
 * dispatched row carries the correct type.
 *
 * Dev mode (no VEROSMS_BASE_URL in tests) means VeroSmsService::send
 * still writes one outbound_sms_logs row per call with type='sent',
 * which gives us a clean side-effect to assert against without hitting
 * the relay.
 */
class OrderUpdateSmsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SmsTemplateSeeder::class);
    }

    private function customer(array $overrides = []): Customer
    {
        return Customer::factory()->create(array_merge([
            'phone' => '09171234567',
            'phone_verified_at' => now(),
            'sms_notifications_enabled' => true,
        ], $overrides));
    }

    private function order(Customer $customer): EcommerceOrder
    {
        return EcommerceOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => EcommerceOrder::STATUS_PENDING,
        ]);
    }

    public function test_transition_to_verified_dispatches_one_sms(): void
    {
        $customer = $this->customer();
        $order = $this->order($customer);

        $order->logStatusChange(EcommerceOrder::STATUS_PENDING, EcommerceOrder::STATUS_VERIFIED);

        $log = OutboundSmsLog::where('phone', $customer->phone)->first();
        $this->assertNotNull($log);
        $this->assertSame(OutboundSmsLog::TYPE_ORDER_UPDATE, $log->type);
    }

    public function test_transition_to_each_notifiable_status_dispatches(): void
    {
        $customer = $this->customer();

        foreach ([
            EcommerceOrder::STATUS_VERIFIED,
            EcommerceOrder::STATUS_PAID,
            EcommerceOrder::STATUS_PREPARING,
            EcommerceOrder::STATUS_PICKED_UP,
            EcommerceOrder::STATUS_CANCELLED,
        ] as $status) {
            $order = $this->order($customer);
            $before = OutboundSmsLog::where('type', OutboundSmsLog::TYPE_ORDER_UPDATE)->count();
            $order->logStatusChange(EcommerceOrder::STATUS_PENDING, $status);
            $after = OutboundSmsLog::where('type', OutboundSmsLog::TYPE_ORDER_UPDATE)->count();

            $this->assertSame($before + 1, $after, "Status {$status} should dispatch exactly one SMS.");
        }
    }

    public function test_transition_to_pending_does_not_dispatch(): void
    {
        $customer = $this->customer();
        $order = $this->order($customer);

        $order->logStatusChange(EcommerceOrder::STATUS_VERIFIED, EcommerceOrder::STATUS_PENDING);

        $this->assertSame(0, OutboundSmsLog::where('type', OutboundSmsLog::TYPE_ORDER_UPDATE)->count());
    }

    public function test_repeat_transition_to_same_status_each_send_its_own_sms(): void
    {
        $customer = $this->customer();
        $order = $this->order($customer);

        $first = $order->logStatusChange(EcommerceOrder::STATUS_PENDING, EcommerceOrder::STATUS_PAID);
        $second = $order->logStatusChange(EcommerceOrder::STATUS_PAID, EcommerceOrder::STATUS_PAID);

        $this->assertSame(2, OutboundSmsLog::where('type', OutboundSmsLog::TYPE_ORDER_UPDATE)->count());

        // Per-row idempotency — each status change row gets its own stamp.
        $this->assertNotNull($first->fresh()->sms_notified_at);
        $this->assertNotNull($second->fresh()->sms_notified_at);
    }

    public function test_opted_out_customer_receives_no_sms(): void
    {
        $customer = $this->customer(['sms_notifications_enabled' => false]);
        $order = $this->order($customer);

        $change = $order->logStatusChange(EcommerceOrder::STATUS_PENDING, EcommerceOrder::STATUS_VERIFIED);

        $this->assertSame(0, OutboundSmsLog::where('type', OutboundSmsLog::TYPE_ORDER_UPDATE)->count());
        $this->assertNull($change->fresh()->sms_notified_at, 'Opt-out skip should NOT stamp sms_notified_at.');
    }

    public function test_customer_without_verified_phone_receives_no_sms(): void
    {
        $customer = $this->customer(['phone_verified_at' => null]);
        $order = $this->order($customer);

        $order->logStatusChange(EcommerceOrder::STATUS_PENDING, EcommerceOrder::STATUS_VERIFIED);

        $this->assertSame(0, OutboundSmsLog::where('type', OutboundSmsLog::TYPE_ORDER_UPDATE)->count());
    }

    public function test_disabled_template_skips_silently(): void
    {
        SmsTemplate::findByKey(SmsTemplate::KEY_ORDER_VERIFIED)->update(['enabled' => false]);

        $customer = $this->customer();
        $order = $this->order($customer);

        $change = $order->logStatusChange(EcommerceOrder::STATUS_PENDING, EcommerceOrder::STATUS_VERIFIED);

        $this->assertSame(0, OutboundSmsLog::where('type', OutboundSmsLog::TYPE_ORDER_UPDATE)->count());
        $this->assertNull($change->fresh()->sms_notified_at, 'Disabled-template skip should NOT stamp sms_notified_at.');
    }

    public function test_missing_template_skips_silently(): void
    {
        SmsTemplate::findByKey(SmsTemplate::KEY_ORDER_VERIFIED)->delete();

        $customer = $this->customer();
        $order = $this->order($customer);

        $order->logStatusChange(EcommerceOrder::STATUS_PENDING, EcommerceOrder::STATUS_VERIFIED);

        $this->assertSame(0, OutboundSmsLog::where('type', OutboundSmsLog::TYPE_ORDER_UPDATE)->count());
    }
}
