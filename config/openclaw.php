<?php

return [
    /*
     | Per-minute rate limit for the openclaw guard, keyed by api_token id.
     */
    'rate_limit_per_minute' => (int) env('OPENCLAW_RATE_LIMIT_PER_MINUTE', 120),
];
