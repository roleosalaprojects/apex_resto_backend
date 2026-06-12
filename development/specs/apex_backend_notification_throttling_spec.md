# apex_backend — Notification Throttling Spec

**Scope:** Stop spamming routine FCM notifications. Let owners batch them per-type (off / immediate / hourly / daily) via a web admin setting. Urgent notifications (higher-access requests, threshold-breach sale/refund/PO alerts) stay instant and are NOT user-configurable.

**Status:** Spec only. No code yet. Implement on a new branch.

**Repo:** `apex_backend` (Laravel)

**Companion changes:** None required in `apex_dashboard` or `apex_pos` — they don't send FCM, they only receive. Forward-compatible.

---

## Step 1 — Create a feature branch

```bash
cd apex_backend
git checkout main
git pull
git checkout -b feature/notification-throttling
```

---

## Background — why this exists

Owners are getting FCM spam. Investigation showed nine `FcmService` send sites. Some are genuinely urgent (need to wake a manager within seconds); the rest are operational/analytics noise that fires whenever a sale lands or a refresh kicks off.

The fix funnels every routine send through a queue, then a scheduled job flushes that queue per the admin's chosen cadence as a single aggregated push per recipient per type. Urgent sends bypass the queue entirely.

---

## Notification classification (locked — do not negotiate per-type defaults without owner sign-off)

### URGENT — always immediate, NOT user-configurable

| Type key | Source | Reason |
|---|---|---|
| `higher_access_request` | `HigherAccessController::notifyHigherAccessRequest()` line 237 | 2-minute expiry. Spec `apex_dashboard_locked_unit_spec.md` depends on this. |
| `large_sale` | `SaleController::processSale()` line 351 | Threshold-breach event; managers want it live. |
| `large_refund` | `SaleController::processSale()` line 339 | Threshold-breach event. |
| `po_approval` | `PurchaseController::submitForApproval()` line 389 | Business-critical approval gate. |

### THROTTLED — admin sets frequency per type (default `hourly`)

| Type key | Source | Default |
|---|---|---|
| `low_stock` | `UpdateItemStocksJob` line 66 | `hourly` |
| `reorder_alert` | `DemandForecastService::generateReorderSuggestions()` line 259 | `hourly` |
| `margin_alert` | `ProfitAnalysisService::getMarginAlerts()` line 159 | `hourly` |
| `late_clockin` | `AttendanceRecord::notifyLateClockIn()` line 202 | `hourly` |
| `ecommerce_order` | `CartPage::placeOrder()` line 118 | `hourly` |

If a new notification type is added later, default it to `hourly` and add a row above.

---

## Step 2 — Database

### Migration A: throttled-notification queue

```php
// database/migrations/2026_xx_xx_create_pending_notifications_table.php
Schema::create('pending_notifications', function (Blueprint $table) {
    $table->id();
    $table->string('type', 50)->index();              // e.g. 'low_stock'
    $table->string('recipient_kind', 30);             // 'user' | 'users' | 'all' | 'permission'
    $table->json('recipient_payload');                // shape varies by recipient_kind (see below)
    $table->string('title');                          // original per-event title (kept for fallback only)
    $table->text('body');                             // original per-event body — used to build the digest line
    $table->json('data')->nullable();                 // original deep-link data payload
    $table->timestamp('created_at')->index();
    $table->timestamp('delivered_at')->nullable()->index();
});
```

`recipient_payload` shape per `recipient_kind`:
- `user`: `{"user_id": 42}`
- `users`: `{"user_ids": [42, 43]}`
- `all`: `{}`
- `permission`: `{"business_user_id": 7, "permission": "invntry"}`

### Migration B: admin preferences

Reuse the existing `business_settings` singleton. Add a JSON column rather than overloading `thresholds`:

```php
// database/migrations/2026_xx_xx_add_notification_preferences_to_business_settings.php
Schema::table('business_settings', function (Blueprint $table) {
    $table->json('notification_preferences')->nullable()->after('supplier_rules');
});
```

Stored shape:

```json
{
  "low_stock":       "hourly",
  "reorder_alert":   "hourly",
  "margin_alert":    "hourly",
  "late_clockin":    "hourly",
  "ecommerce_order": "hourly"
}
```

Allowed values per key: `"off" | "immediate" | "hourly" | "daily"`. Anything else → treat as `hourly` (the safe default).

---

## Step 3 — `FcmService` choke point

All nine senders already funnel through `App\Services\FcmService`. Introduce a single private router method at the top of every public send method:

```php
// app/Services/FcmService.php

/**
 * Decide whether this send goes out now or is queued for a digest.
 * Returns true if the send was queued (caller should not send directly).
 */
private function routeOrQueue(
    string $type,
    string $recipientKind,
    array $recipientPayload,
    string $title,
    string $body,
    array $data
): bool {
    if (in_array($type, self::URGENT_TYPES, true)) {
        return false; // bypass queue, send now
    }

    $freq = app(NotificationPreferenceService::class)->frequencyFor($type);

    if ($freq === 'off')       { return true; }   // swallow entirely
    if ($freq === 'immediate') { return false; }  // send now

    \DB::table('pending_notifications')->insert([
        'type'              => $type,
        'recipient_kind'    => $recipientKind,
        'recipient_payload' => json_encode($recipientPayload),
        'title'             => $title,
        'body'              => $body,
        'data'              => json_encode($data),
        'created_at'        => now(),
    ]);

    return true;
}

private const URGENT_TYPES = [
    'higher_access_request',
    'large_sale',
    'large_refund',
    'po_approval',
];
```

Update every public send method (`sendToUser`, `sendToUsers`, `sendToAll`, `sendToUsersWithPermission`) to call `routeOrQueue` first and short-circuit if it returns `true`:

```php
public function sendToUsersWithPermission(int $businessUserId, string $permission, string $title, string $body, array $data): int
{
    $type = $data['type'] ?? 'unknown';

    if ($this->routeOrQueue(
        $type,
        'permission',
        ['business_user_id' => $businessUserId, 'permission' => $permission],
        $title, $body, $data
    )) {
        return 0;
    }

    // ... existing immediate-send code unchanged ...
}
```

Do the same for the other three public methods, threading their existing recipient args into `recipient_payload`.

**The `$data['type']` convention is already in use** at every existing call site — no caller changes needed.

---

## Step 4 — `NotificationPreferenceService`

```php
// app/Services/NotificationPreferenceService.php
namespace App\Services;

use App\Models\BusinessSettings;

class NotificationPreferenceService
{
    private const DEFAULTS = [
        'low_stock'       => 'hourly',
        'reorder_alert'   => 'hourly',
        'margin_alert'    => 'hourly',
        'late_clockin'    => 'hourly',
        'ecommerce_order' => 'hourly',
    ];

    private const ALLOWED = ['off', 'immediate', 'hourly', 'daily'];

    public function frequencyFor(string $type): string
    {
        $prefs = BusinessSettings::current()->notification_preferences ?? [];
        $value = $prefs[$type] ?? self::DEFAULTS[$type] ?? 'immediate';
        return in_array($value, self::ALLOWED, true) ? $value : 'hourly';
    }

    public function all(): array
    {
        $prefs = BusinessSettings::current()->notification_preferences ?? [];
        return array_merge(self::DEFAULTS, $prefs);
    }

    public function update(array $input): void
    {
        $clean = [];
        foreach (self::DEFAULTS as $type => $_) {
            if (isset($input[$type]) && in_array($input[$type], self::ALLOWED, true)) {
                $clean[$type] = $input[$type];
            }
        }
        $settings = BusinessSettings::current();
        $settings->notification_preferences = $clean;
        $settings->save();
    }
}
```

Cast the new column in `BusinessSettings`:

```php
// app/Models/BusinessSettings.php
protected $casts = [
    // ... existing casts ...
    'notification_preferences' => 'array',
];
```

---

## Step 5 — Digest sender

### Command

```php
// app/Console/Commands/SendNotificationDigest.php
namespace App\Console\Commands;

use App\Services\FcmService;
use App\Services\NotificationPreferenceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendNotificationDigest extends Command
{
    protected $signature = 'notification:send-digest {--frequency=hourly}';
    protected $description = 'Flush pending FCM notifications for the given frequency tier';

    public function handle(NotificationPreferenceService $prefs, FcmService $fcm): int
    {
        $frequency = $this->option('frequency');
        $eligibleTypes = array_keys(array_filter(
            $prefs->all(),
            fn ($f) => $f === $frequency
        ));

        if (empty($eligibleTypes)) {
            $this->info("No types configured for frequency={$frequency}.");
            return self::SUCCESS;
        }

        $pending = DB::table('pending_notifications')
            ->whereIn('type', $eligibleTypes)
            ->whereNull('delivered_at')
            ->orderBy('created_at')
            ->get();

        // Group by (type, recipient_kind, recipient_payload) — each recipient gets one push per type
        $grouped = $pending->groupBy(fn ($row) => $row->type . '|' . $row->recipient_kind . '|' . $row->recipient_payload);

        foreach ($grouped as $rows) {
            $first   = $rows->first();
            $count   = $rows->count();
            $type    = $first->type;
            $payload = json_decode($first->recipient_payload, true);

            [$title, $body] = $this->formatDigest($type, $rows);

            $data = ['type' => $type . '_digest', 'count' => $count];

            // Use a bypass entry-point so the digest doesn't re-queue itself.
            $fcm->sendDigestBypass(
                $first->recipient_kind,
                $payload,
                $title,
                $body,
                $data
            );
        }

        DB::table('pending_notifications')
            ->whereIn('id', $pending->pluck('id'))
            ->update(['delivered_at' => now()]);

        $this->info("Flushed " . $pending->count() . " pending notifications across " . $grouped->count() . " digests.");
        return self::SUCCESS;
    }

    private function formatDigest(string $type, $rows): array
    {
        $count    = $rows->count();
        $label    = match ($type) {
            'low_stock'       => 'Stock Alerts',
            'reorder_alert'   => 'Reorder Alerts',
            'margin_alert'    => 'Margin Alerts',
            'late_clockin'    => 'Late Clock-ins',
            'ecommerce_order' => 'New Online Orders',
            default           => 'Alerts',
        };

        // Take the leading subject of each body (e.g., "{itemName} — 3 remaining" → "{itemName}")
        $subjects = $rows->take(5)->map(fn ($r) => trim(explode('—', $r->body)[0]))->all();
        $tail     = $count > 5 ? " and " . ($count - 5) . " more" : '';

        $title = "{$label} ({$count})";
        $body  = implode(', ', $subjects) . $tail;

        return [$title, $body];
    }
}
```

### `FcmService::sendDigestBypass`

Add a single public method that performs the actual send without re-entering `routeOrQueue`:

```php
// app/Services/FcmService.php
public function sendDigestBypass(string $recipientKind, array $payload, string $title, string $body, array $data): int
{
    return match ($recipientKind) {
        'user'       => $this->dispatchToTokens($this->tokensForUser($payload['user_id']),                       $title, $body, $data),
        'users'      => $this->dispatchToTokens($this->tokensForUsers($payload['user_ids']),                     $title, $body, $data),
        'all'        => $this->dispatchToTokens($this->tokensForAll(),                                            $title, $body, $data),
        'permission' => $this->dispatchToTokens($this->tokensForPermission($payload['business_user_id'], $payload['permission']), $title, $body, $data),
        default      => 0,
    };
}
```

(`tokensForUser`/`tokensForUsers`/`tokensForAll`/`tokensForPermission` already exist inside the service — extract them from the existing public methods so both paths share the same token-resolution logic.)

### Schedule

```php
// routes/console.php — add to the existing schedule block
Schedule::command('notification:send-digest --frequency=hourly')
    ->hourly()
    ->name('notif-digest-hourly')
    ->withoutOverlapping();

Schedule::command('notification:send-digest --frequency=daily')
    ->dailyAt(config('notifications.daily_summary_time', '20:00'))
    ->name('notif-digest-daily')
    ->withoutOverlapping();
```

Daily digest reuses the same hour as the existing `SendDailySalesSummary` for predictability — owner gets one quiet evening sweep instead of two.

---

## Step 6 — Web admin UI

### Route + controller

```php
// routes/admin.php — add inside the existing settings group
Route::get('/settings/notifications',  [NotificationPreferencesController::class, 'index'])->name('admin.settings.notifications');
Route::post('/settings/notifications', [NotificationPreferencesController::class, 'update'])->name('admin.settings.notifications.update');
```

```php
// app/Http/Controllers/Admin/NotificationPreferencesController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\NotificationPreferenceService;
use Illuminate\Http\Request;

class NotificationPreferencesController extends Controller
{
    public function index(NotificationPreferenceService $prefs)
    {
        return view('admin.settings.notifications.index', [
            'preferences' => $prefs->all(),
            'options'     => ['off', 'immediate', 'hourly', 'daily'],
        ]);
    }

    public function update(Request $request, NotificationPreferenceService $prefs)
    {
        $prefs->update($request->input('preferences', []));
        return back()->with('status', 'Notification preferences updated.');
    }
}
```

### View

`resources/views/admin/settings/notifications/index.blade.php` — a simple table: notification type on the left, a `<select>` with off/immediate/hourly/daily on the right, one Save button at the bottom. Match the styling of `admin/settings/pos-settings.blade.php`.

Include a non-editable info row at the top listing the four URGENT types with a lock icon and the caption "Always sent immediately — required for time-sensitive workflows."

---

## Step 7 — Tests

- `tests/Unit/Services/NotificationPreferenceServiceTest.php`
  - Returns hourly default when nothing is set
  - Returns admin override when set
  - Rejects invalid values (falls back to hourly)
- `tests/Feature/FcmThrottlingTest.php`
  - `higher_access_request` always bypasses the queue (assert immediate send, no row in `pending_notifications`)
  - `low_stock` with default config inserts into `pending_notifications` and does NOT call the HTTP path
  - `low_stock` with `immediate` setting sends now
  - `low_stock` with `off` setting neither inserts nor sends
- `tests/Feature/NotificationDigestCommandTest.php`
  - Three `low_stock` events to the same recipient → single digest push with `(3)` in the title
  - Mixed types in queue → separate pushes per type
  - Daily-tier events are not flushed by the hourly command, and vice versa
  - After flush, `delivered_at` is set on flushed rows

---

## What does NOT change

- The nine FCM caller sites — they all already pass `$data['type']`, which is the only contract `routeOrQueue` reads.
- Dashboard and POS clients — they receive the same FCM message shape; digests just have a different `data.type` suffix (`_digest`).
- Higher-access flow — fully bypasses the new code path.
- `device_tokens` table.
- Existing `notification:daily-sales-summary` command.

---

## Rollout

1. Ship the migration, the service, and the queue-routing in `FcmService`. With no admin action, every routine notification immediately starts batching hourly. Spam relief is automatic on deploy.
2. Ship the admin UI in the same release so owners can dial individual types up or down.
3. No client-side rollout dependency.

If an owner needs the old behavior for a specific type, they set it to `immediate` in the new admin page. If a type is broken/unwanted entirely, they set it to `off`.

---

## Cross-reference

- Companion spec (dashboard surface for one of the urgent types): `development/specs/apex_dashboard_locked_unit_spec.md`
- Choke point already exists: `app/Services/FcmService.php` — all sends route through it today, so caller-side fanout is zero.
- Existing scheduled-command precedent: `app/Console/Commands/SendDailySalesSummary.php` + `routes/console.php` line 51.
- Settings singleton pattern: `app/Models/BusinessSettings.php` (`current()` factory).
