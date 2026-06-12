<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY', ''),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-5-20250929'),
        'timeout' => env('ANTHROPIC_TIMEOUT', 120),
    ],

    'ollama' => [
        'url' => env('OLLAMA_URL', 'http://192.168.0.6:11434'),
        'model' => env('OLLAMA_MODEL', 'qwen3:8b'),
        'timeout' => env('OLLAMA_TIMEOUT', 120),
    ],

    'openweathermap' => [
        'api_key' => env('OPENWEATHERMAP_API_KEY', ''),
        'base_url' => env('OPENWEATHERMAP_BASE_URL', 'https://api.openweathermap.org/data/2.5'),
        'timeout' => env('OPENWEATHERMAP_TIMEOUT', 15),
    ],

    'fcm' => [
        'project_id' => env('FCM_PROJECT_ID'),
        'credentials' => env('FCM_CREDENTIALS', storage_path('app/firebase-credentials.json')),
    ],

    /*
    | Outbound SMS — provider-agnostic configuration.
    |
    | `driver` picks which SmsRelayContract implementation is bound in
    | AppServiceProvider. Flipping it on prod is the rollback path if
    | the new provider misbehaves.
    |
    | OTP behavior (cooldown, hourly cap, lockout, code TTL) lives here
    | rather than on each provider so swapping the driver doesn't
    | reset rate limits or change customer-facing timing.
    */
    'sms' => [
        'driver' => env('SMS_RELAY', 'verosms'),
        'otp' => [
            'cooldown_seconds' => env('SMS_OTP_COOLDOWN', 60),
            'max_send_per_hour' => env('SMS_OTP_MAX_SEND_PER_HOUR', 5),
            'max_verify_attempts' => env('SMS_OTP_MAX_VERIFY', 5),
            'ttl_minutes' => env('SMS_OTP_TTL_MINUTES', 10),
        ],
    ],

    /*
    | VeroSMS — Android SMS relay we use for customer phone OTPs at
    | /shop registration. When `base_url` is empty we run in dev
    | mode: the OTP is logged to laravel.log instead of being sent,
    | so local development doesn't need an Android device on the LAN.
    */
    'verosms' => [
        'base_url' => env('VEROSMS_BASE_URL'),
        'api_key' => env('VEROSMS_API_KEY'),
        'device_id' => env('VEROSMS_DEVICE_ID'),
        'sim' => env('VEROSMS_SIM', 'sim1'),
        'timeout' => env('VEROSMS_TIMEOUT', 15),
        'otp_ttl_minutes' => env('VEROSMS_OTP_TTL_MINUTES', 10),
        'otp_send_cooldown_seconds' => env('VEROSMS_OTP_COOLDOWN', 60),
        'otp_max_send_per_hour' => env('VEROSMS_OTP_MAX_SEND_PER_HOUR', 5),
        'otp_max_verify_attempts' => env('VEROSMS_OTP_MAX_VERIFY', 5),
    ],

    /*
    | SMS Gateway for Android (sms-gate.app) — open-source replacement
    | for VeroSMS. Mint a JWT via /auth/token with Basic credentials,
    | use Bearer on every other call. Cloud base:
    |   https://api.sms-gate.app/3rdparty/v1
    | Self-host on a private VPN: http://<host>:3000/api
    */
    'sms_gate' => [
        'base_url' => env('SMS_GATE_BASE_URL', 'https://api.sms-gate.app/3rdparty/v1'),
        // 'basic' works for both the cloud server (legacy) and the
        // local-on-phone server (the only mode it supports). 'jwt' is
        // recommended by upstream for cloud / private-server modes —
        // mints once per ~55 minutes, then Bearer on every request.
        'auth_mode' => env('SMS_GATE_AUTH', 'basic'),
        // Endpoint path naming diverges between modes:
        //   - Local Android server: singular `/message`
        //   - Cloud 3rdparty/v1:    plural   `/messages`
        // Default favors local since that's the more constrained case;
        // override to /messages when pointing at the cloud API.
        'messages_path' => env('SMS_GATE_MESSAGES_PATH', '/message'),
        // Request body shape also diverges:
        //   - 'local'  → flat `{message: string}`  (Android app)
        //   - 'cloud'  → nested `{textMessage: {text: string}}`
        // Sending the wrong shape gets you a Kotlin NPE from the
        // Android app instead of a useful 4xx. Pick by your relay.
        'payload_flavor' => env('SMS_GATE_PAYLOAD_FLAVOR', 'local'),
        'username' => env('SMS_GATE_USERNAME'),
        'password' => env('SMS_GATE_PASSWORD'),
        'device_id' => env('SMS_GATE_DEVICE_ID'),
        'sim' => (int) env('SMS_GATE_SIM', 1),
        'timeout' => env('SMS_GATE_TIMEOUT', 15),
        // HMAC-SHA256 signing key for webhook delivery callbacks.
        // Set the same value in the SMS Gateway Android app's webhook
        // settings, then in this env var. If empty, the webhook
        // endpoint rejects every request (fail-closed).
        'webhook_signing_key' => env('SMS_GATE_WEBHOOK_SIGNING_KEY'),
        // URL we ask the relay to POST callbacks to. Must be reachable
        // FROM the relay (the Android phone, or the cloud server) —
        // for the local-on-LAN case that's your Mac's LAN IP + Sail
        // port, not localhost. Falls back to APP_URL + /webhooks/sms-gate.
        // To enable Basic auth, embed credentials in the URL:
        //   http://user:pass@192.168.0.42:80/webhooks/sms-gate
        // The relay's HTTP client honors URL-embedded credentials and
        // sends them in the Authorization header.
        'webhook_url' => env('SMS_GATE_WEBHOOK_URL'),
        // Defense-in-depth on the webhook endpoint:
        //  1. webhook_allowed_ips  — drop anything not from the relay
        //  2. webhook_basic_*      — require HTTP Basic on top of HMAC
        //  3. webhook_signing_key  — HMAC body+timestamp (always on)
        // Each layer catches a different attack class. All three are
        // optional EXCEPT signing_key (which is mandatory in this build);
        // setting them is recommended when the endpoint is reachable
        // from outside a private LAN.
        'webhook_basic_user' => env('SMS_GATE_WEBHOOK_BASIC_USER'),
        'webhook_basic_password' => env('SMS_GATE_WEBHOOK_BASIC_PASSWORD'),
        'webhook_allowed_ips' => env('SMS_GATE_WEBHOOK_ALLOWED_IPS'),
    ],

];
