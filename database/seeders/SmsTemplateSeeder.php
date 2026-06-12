<?php

namespace Database\Seeders;

use App\Models\SmsTemplate;
use Illuminate\Database\Seeder;

class SmsTemplateSeeder extends Seeder
{
    /**
     * Idempotent — re-running won't clobber admin edits because we
     * only touch the description on existing rows (body + enabled
     * stay whatever the admin set). Use `firstOrCreate` so a re-seed
     * after a manual delete restores the missing key.
     */
    public function run(): void
    {
        $defaults = [
            [
                'key' => SmsTemplate::KEY_ORDER_VERIFIED,
                'description' => 'Sent when an order moves to Verified.',
                'body' => '{brand}: Order {reference} verified. We\'re preparing your order now.',
            ],
            [
                'key' => SmsTemplate::KEY_ORDER_PAID,
                'description' => 'Sent when an order is marked Paid.',
                'body' => '{brand}: Payment received for order {reference}. Total: {total}. We\'ll text you when it\'s ready.',
            ],
            [
                'key' => SmsTemplate::KEY_ORDER_PREPARING,
                'description' => 'Sent when an order moves to Preparing.',
                'body' => '{brand}: Your order {reference} is being prepared.',
            ],
            [
                'key' => SmsTemplate::KEY_ORDER_PICKED_UP,
                'description' => 'Sent when an order is marked Picked Up.',
                'body' => '{brand}: Order {reference} has been picked up. Thanks for shopping with us!',
            ],
            [
                'key' => SmsTemplate::KEY_ORDER_CANCELLED,
                'description' => 'Sent when an order is cancelled.',
                'body' => '{brand}: Order {reference} has been cancelled. Reply or call us if this is unexpected.',
            ],
        ];

        foreach ($defaults as $row) {
            SmsTemplate::firstOrCreate(
                ['key' => $row['key']],
                [
                    'description' => $row['description'],
                    'body' => $row['body'],
                    'enabled' => true,
                ]
            );
        }
    }
}
