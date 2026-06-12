<?php

return [
    'large_sale_threshold' => env('NOTIFICATION_LARGE_SALE_THRESHOLD', 10000),
    'large_refund_threshold' => env('NOTIFICATION_LARGE_REFUND_THRESHOLD', 5000),
    'daily_summary_time' => env('NOTIFICATION_DAILY_SUMMARY_TIME', '20:00'),
    'order_feed_poll_ms' => (int) env('NAVBAR_ORDER_FEED_POLL_MS', 10000),
    'access_request_poll_ms' => (int) env('NAVBAR_ACCESS_REQUEST_POLL_MS', 3000),
];
