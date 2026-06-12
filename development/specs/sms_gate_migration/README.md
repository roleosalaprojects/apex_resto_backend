# SMS Gate migration — replacing / supplementing VeroSMS

## Context

VeroSMS (the Cloudflare-fronted Android relay we wired in this session at `app/Services/VeroSmsService.php`) has been **failing on prod**. Symptoms observed during testing this session:

- HTTP `/api/send/sms` returns `{status:true, sms_id:N}` — the relay accepts requests.
- Status polling on the same id either returns Cloudflare 404 HTML pages for older ids, or sticks at `vero_code=2 (processing)` indefinitely.
- Zero of the dispatched SMS actually reach the carrier — the Android relay device isn't transmitting.

We've already paid the integration cost for an Android-relay model (OTP, order-update SMS, admin SMS log surface, scheduled poller, template editor). What we need is to swap the **underlying relay** without rewriting the rest of the pipeline.

**[SMS Gateway for Android](https://docs.sms-gate.app)** — also known as "SMS Gate" or `sms-gate.app` — is an open-source, drop-in replacement for that role. It's the same shape (Android phone acts as the gateway) but with a modern REST API, JWT auth, webhook delivery callbacks, and three deployment modes including self-hosted.

This document is the integrator's reference for swapping it in.

---

## What SMS Gate is

| Aspect | SMS Gate |
|---|---|
| Android version required | 5.0+ |
| Deployment modes | Local Server (LAN only, no account), Public Cloud (api.sms-gate.app), Private Server (self-hosted) |
| Auth | JWT Bearer (recommended) or HTTP Basic (legacy) |
| Multi-device | Yes — load-distribute across multiple phones |
| Delivery callbacks | Webhooks (`sms:sent`, `sms:delivered`, `sms:failed`, …) with HMAC-SHA256 signature |
| OpenAPI spec | <https://capcom6.github.io/android-sms-gateway/> |

The cloud base URL is `https://api.sms-gate.app/3rdparty/v1`. Self-hosted runs the same paths under `http://<your-host>:3000/api`. Local-only mode runs `http://<phone-ip>:8080/`.

---

## API surface (the bits we need)

All bodies are JSON; all responses are JSON. Endpoint paths below are relative to the chosen base URL.

### Auth

**Get a JWT** — `POST /auth/token`, authed with Basic.
```bash
curl -u "$USERNAME:$PASSWORD" \
  -H 'Content-Type: application/json' \
  -d '{"scopes":["messages:write","messages:read"],"ttl":3600}' \
  https://api.sms-gate.app/3rdparty/v1/auth/token
```
Response includes `access_token`, `refresh_token`, `expires_at`. After that every request uses:
```
Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
```

**Refresh** — `POST /auth/token/refresh` with the refresh token. Each refresh rotates the pair.

### Send

**`POST /messages`** — fire one message at up to 100 recipients.

Body (only the fields we need):
```json
{
  "phoneNumbers": ["+639171234567"],
  "textMessage": { "text": "Order ECO-… verified." },
  "simNumber": 1,
  "withDeliveryReport": true,
  "ttl": 600
}
```

Optional but useful: `deviceId` (route to a specific phone in multi-device deployments), `id` (caller-supplied uuid for idempotency), `priority` (-128/0/100/127), `scheduleAt`, `validUntil`.

Response (`202 Accepted`):
```json
{
  "id": "01H8WAM…",
  "deviceId": "DEV…",
  "state": "Pending",
  "recipients": [{ "phoneNumber": "+639171234567", "state": "Pending" }]
}
```

### Status check

**`GET /messages/{id}`** — same shape as the send response, with the current `state` and per-recipient states.

Message lifecycle: `Pending → Processed → Sent → Delivered → Failed`.

### Webhooks (the real win)

Instead of polling every 2 min like we do with VeroSMS, SMS Gate POSTs us when each transition happens.

| Event | When | Payload (relevant fields) |
|---|---|---|
| `sms:sent` | All parts handed to the carrier | `messageId, recipient, simNumber, partsCount, sentAt` |
| `sms:delivered` | Per-part delivery confirmation | `messageId, recipient, simNumber, deliveredAt` |
| `sms:failed` | Any part failed | `messageId, recipient, simNumber, reason, failedAt` |
| `sms:received` | Inbound SMS (if we ever want it) | `messageId, message, sender, recipient, simNumber, receivedAt` |

Every callback carries `X-Signature` (HMAC-SHA256 of the body) and `X-Timestamp` headers; verify both to drop replays.

Retry policy on our side: SMS Gate retries with exponential backoff (10s, 20s, 40s, …) up to ~14 attempts over ~2 days if we don't return `2xx` within 30 seconds.

Register webhooks via `POST /webhooks` with `{ event, url, deviceId? }`.

---

## Why this beats our current VeroSMS pipeline

| Concern | VeroSMS | SMS Gate |
|---|---|---|
| API style | Bespoke GET-with-querystring | REST + JSON |
| Auth | Bearer token (shared secret) | JWT (rotating) or Basic |
| Delivery status | Pull-only (`/api/check/status?id=…`), Cloudflare-fronted | Push via webhook + pull |
| Reliability story | Closed-source, opaque queue, Cloudflare 404s on older ids | Open-source, message-state lifecycle, retries documented |
| Self-host | No | Yes (Private Server mode) |
| Multi-SIM / multi-device | Single device | First-class |
| Idempotency | None — caller can't pass an id | `id` field accepts a caller-supplied uuid |

---

## Migration plan

### Phase 1 — abstract the dispatch surface (no behavior change)

The clean move is to make `VeroSmsService` an implementation of an interface, then write a parallel `SmsGateService` against the same interface. Everything downstream (controllers, jobs, observers) stays put.

**New contract** — `app/Contracts/SmsRelayContract.php`:

```php
interface SmsRelayContract
{
    /** Returns ['status' => RESULT_*, 'message' => ..., 'sms_id' => ?string]. */
    public function send(string $phone, string $message, string $type, ?string $ip = null): array;

    /** Issues an OTP — same return shape as VeroSmsService::sendOtp. */
    public function sendOtp(string $phone, ?string $ip = null): array;

    /** Verifies an OTP. */
    public function verify(string $phone, string $code): bool;

    /** Polls the relay for the current state of one log row. */
    public function pollStatus(\App\Models\OutboundSmsLog $log): ?\App\Models\OutboundSmsLog;

    /** Canonical 09XXXXXXXXX form. */
    public function normalizePhone(string $phone): string;
}
```

Bind it in `AppServiceProvider::register()`:

```php
$this->app->bind(SmsRelayContract::class, function () {
    return match (config('services.sms.driver', 'verosms')) {
        'sms_gate' => app(SmsGateService::class),
        default    => app(VeroSmsService::class),
    };
});
```

Existing call sites already type-hint `VeroSmsService` — refactor them to inject the contract instead. Touchpoints:

- `app/Http/Controllers/Customer/AuthController.php` (`sendRegisterOtp`, `register`)
- `app/Http/Controllers/Customer/ProfileController.php` (`update`, `sendPhoneOtp`)
- `app/Http/Requests/Customer/RegisterRequest.php` (`prepareForValidation`)
- `app/Http/Requests/Customer/UpdateProfileRequest.php` (`normalizedSubmittedPhone`)
- `app/Jobs/Ecommerce/SendOrderUpdateSmsJob.php`
- `app/Jobs/PollVeroSmsStatusJob.php` (rename to `PollSmsRelayStatusJob`)
- `app/Http/Controllers/Admin/Settings/OutboundSmsLogController.php` (refresh + bulk poll)

This phase is pure refactor — no behavior change, no env change, prod stays on VeroSMS.

### Phase 2 — implement `SmsGateService`

New file `app/Services/SmsGateService.php` implementing `SmsRelayContract`. Internals:

- Maintain a JWT cache (`Cache::remember('sms_gate:jwt', 3500, fn () => $this->mintToken())`); refresh on `401`.
- `send()` → `POST /messages` with `phoneNumbers, textMessage, withDeliveryReport:true, ttl:600`. Store the SMS Gate message `id` in `OutboundSmsLog.sms_id` (it's a string here — confirm the column type, may need a migration).
- `pollStatus()` → `GET /messages/{id}`; map `state` → our `STATUS_SENT|PROCESSING|DELIVERED|FAILED`. Easiest mapping:
  - `Pending`, `Processed` → `STATUS_SENT`
  - `Sent` → `STATUS_PROCESSING`
  - `Delivered` → `STATUS_DELIVERED`
  - `Failed` → `STATUS_FAILED`
- `sendOtp()` / `verify()` reuse the existing OTP table + cooldown logic verbatim — only the network layer changes.

Config (`config/services.php`):

```php
'sms' => [
    'driver' => env('SMS_RELAY', 'verosms'),
],
'sms_gate' => [
    'base_url' => env('SMS_GATE_BASE_URL', 'https://api.sms-gate.app/3rdparty/v1'),
    'username' => env('SMS_GATE_USERNAME'),
    'password' => env('SMS_GATE_PASSWORD'),
    'device_id' => env('SMS_GATE_DEVICE_ID'),
    'sim'       => (int) env('SMS_GATE_SIM', 1),
    'webhook_signing_key' => env('SMS_GATE_WEBHOOK_SIGNING_KEY'),
],
```

### Phase 3 — webhook handler (replaces polling)

New endpoint at `POST /webhooks/sms-gate/{event}` (or one endpoint that branches on the body's event field):

1. Read `X-Signature` + `X-Timestamp`.
2. Reject if `abs(now - timestamp) > 5 minutes` (replay window).
3. Recompute HMAC-SHA256 with `config('services.sms_gate.webhook_signing_key')` against the raw body; `hash_equals` against the header.
4. Find the matching `OutboundSmsLog` by `sms_id = $body['messageId']`.
5. Map event → status (`sms:sent` → processing; `sms:delivered` → delivered; `sms:failed` → failed + persist `reason` into the `error` column).
6. Update `last_checked_at = now()`, save, return `204`.

Register the webhook once after deploy via `POST /webhooks` for each event we care about. One-time bootstrap command: `php artisan sms-gate:register-webhooks`.

Once webhooks land cleanly, the existing `sms-logs:poll-pending` scheduled job becomes a safety-net rather than the primary path — keep it running on a longer cadence (every 10 min instead of every 2).

### Phase 4 — flip the flag

In `.env` on prod: `SMS_RELAY=sms_gate`. Everything else stays the same. Roll back instantly by flipping the flag to `verosms`.

---

## Things to confirm before shipping

- [ ] **`OutboundSmsLog.sms_id` column type**. VeroSMS gave us integers; SMS Gate gives strings (ULID-like). May need to convert the column to `string` and reindex.
- [ ] **Phone number format**. SMS Gate examples use `+639171234567` (E.164). Our `VeroSmsService::normalizePhone` returns the 09XXX form. For the SMS Gate path, the service should convert to `+63…` at the network boundary; storage stays in 09XXX form so admin search keeps working.
- [ ] **Webhook URL must be public**. If we self-host SMS Gate on the same VPN/LAN as the Laravel app, callbacks stay private. If we use Public Cloud (`api.sms-gate.app`), the Laravel webhook handler must be reachable from the internet. Cloudflare in front of `leteres.com` already covers this.
- [ ] **Replay-attack window**. The 5-minute clock-skew tolerance assumes prod NTP is sane. Worth checking the prod time delta vs UTC before going live.
- [ ] **HMAC body shape**. The signing input is the raw request body — read it with `$request->getContent()` BEFORE Laravel parses the JSON; otherwise the bytes can shift (whitespace, ordering) and signatures will mismatch.

---

## Out of scope (deferred)

- **Sender-ID branding** (e.g. "QuickBaskets" instead of the SIM number). Some carriers in PH require explicit registration; not relevant for the relay model since we send via a SIM, not via a carrier API.
- **Two-way SMS** (`sms:received` webhook). The plumbing supports it but we have no use case yet.
- **MMS**. Same as above.
- **Cost analytics**. Per-message costs depend on the SIM's plan; can't model from inside the app.

---

## Useful pages

- Project home: <https://docs.sms-gate.app>
- Authentication: <https://docs.sms-gate.app/integration/authentication/>
- API overview: <https://docs.sms-gate.app/integration/api/>
- OpenAPI spec: <https://capcom6.github.io/android-sms-gateway/>
- Webhooks: <https://docs.sms-gate.app/features/webhooks/>
- Status tracking: <https://docs.sms-gate.app/features/status-tracking/>
- Sending messages: <https://docs.sms-gate.app/features/sending-messages/>
