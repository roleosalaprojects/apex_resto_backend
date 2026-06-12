<?php

namespace App\Jobs\Ecommerce;

use App\Contracts\SmsRelayContract;
use App\Models\Ecommerce\EcommerceOrder;
use App\Models\Ecommerce\EcommerceOrderStatusChange;
use App\Models\OutboundSmsLog;
use App\Models\SmsTemplate;
use App\Services\BrandingService;
use App\Services\SmsTemplateRenderer;
use App\Services\VeroSmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Dispatched when an EcommerceOrderStatusChange row is created. Looks
 * up the matching template by to_status, renders it with the order's
 * data, fires the SMS, and stamps sms_notified_at on the SAME status
 * change row so we never double-send (re-dispatch returns early).
 *
 * Any "skip" reason (no template, no phone, opted out, template
 * disabled) is silent — these are valid states, not errors.
 */
class SendOrderUpdateSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 20;

    public function __construct(public int $statusChangeId) {}

    public function handle(
        SmsRelayContract $sms,
        SmsTemplateRenderer $renderer,
        BrandingService $branding,
    ): void {
        $change = EcommerceOrderStatusChange::with('order.customer')->find($this->statusChangeId);

        if (! $change || $change->sms_notified_at) {
            return;
        }

        $order = $change->order;
        $customer = $order?->customer;

        if (! $order || ! $customer) {
            return;
        }

        if (! $customer->sms_notifications_enabled) {
            return;
        }

        if (! $customer->phone || ! $customer->phone_verified_at) {
            return;
        }

        $key = $this->keyFor((int) $change->to_status);

        if ($key === null) {
            return;
        }

        $brand = $branding->forStorefront()['brand_name'] ?? 'APEX';

        $message = $renderer->render($key, [
            'brand' => $brand,
            'reference' => $order->reference,
            'customer_name' => $customer->name,
            'total' => number_format((float) $order->total, 2),
        ]);

        if ($message === null) {
            return;
        }

        $result = $sms->send($customer->phone, $message, OutboundSmsLog::TYPE_ORDER_UPDATE);

        // Stamp regardless of relay result — VeroSmsService already
        // logged the failure row. Re-stamping would lead to retries on
        // every observer fire, which we don't want; admins can manually
        // null this column to re-send if the relay had a real outage.
        $change->forceFill(['sms_notified_at' => now()])->save();

        if (($result['status'] ?? null) !== VeroSmsService::RESULT_OK) {
            Log::warning('Order update SMS dispatch failed', [
                'status_change_id' => $change->id,
                'order_id' => $order->id,
                'result' => $result,
            ]);
        }
    }

    /** Maps an EcommerceOrder status integer to a template key, or null to skip. */
    private function keyFor(int $toStatus): ?string
    {
        return match ($toStatus) {
            EcommerceOrder::STATUS_VERIFIED => SmsTemplate::KEY_ORDER_VERIFIED,
            EcommerceOrder::STATUS_PAID => SmsTemplate::KEY_ORDER_PAID,
            EcommerceOrder::STATUS_PREPARING => SmsTemplate::KEY_ORDER_PREPARING,
            EcommerceOrder::STATUS_PICKED_UP => SmsTemplate::KEY_ORDER_PICKED_UP,
            EcommerceOrder::STATUS_CANCELLED => SmsTemplate::KEY_ORDER_CANCELLED,
            default => null,
        };
    }
}
