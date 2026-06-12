# Push Notifications

## Overview

Push notifications are delivered from the Laravel backend to the **Apex Dashboard** Flutter app via **Firebase Cloud Messaging (FCM) v1 HTTP API**. The system is permission-based — notifications are routed only to users whose roles grant them the relevant permission.

### How It Works (Simple Version)

1. **Registration**: When a user logs in on the dashboard app, the app obtains an FCM device token from Firebase and sends it to the backend (`POST /device-tokens`). The backend stores it in the `device_tokens` table.
2. **Trigger**: When something notable happens (large sale, refund, late clock-in, etc.), the backend calls `FcmService` to send a notification.
3. **Delivery**: `FcmService` looks up device tokens for the target users, then sends the message to Firebase, which pushes it to each device.
4. **Display**: The Flutter app receives the message and either shows a local notification (foreground) or a system notification (background/terminated).
5. **Navigation**: Each notification carries a `type` and optional `targetId`. When tapped, the app navigates to the appropriate page.
6. **Cleanup**: On logout, the app unregisters its token from the backend and deletes it from Firebase.

---

## Backend Architecture

### Key Files

| Component | Path |
|---|---|
| FCM Service | `app/Services/FcmService.php` |
| Device Token Model | `app/Models/DeviceToken.php` |
| Device Token Controller | `app/Http/Controllers/API/v1/mobile/DeviceTokenController.php` |
| Device Token Migration | `database/migrations/2026_02_25_000001_create_device_tokens_table.php` |
| Notifications Config | `config/notifications.php` |
| FCM Config | `config/services.php` (`fcm` key) |
| Daily Summary Command | `app/Console/Commands/SendDailySalesSummary.php` |
| Test Notification Command | `app/Console/Commands/SendTestNotification.php` |
| Tests | `tests/Feature/Notifications/` |

### Device Token API

Defined in `routes/api/mobile.php`, protected by authentication middleware.

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/device-tokens` | Register a device token |
| `DELETE` | `/device-tokens` | Unregister a device token |

**Registration payload:**

```json
{
  "token": "fcm-device-token-string",
  "platform": "android|ios"
}
```

### FcmService

Located at `app/Services/FcmService.php`. Core methods:

| Method | Description |
|---|---|
| `sendToUser($userId, $title, $body, $data)` | Send to all devices of a single user |
| `sendToUsers($userIds, ...)` | Send to multiple users |
| `sendToAll(...)` | Broadcast to all registered devices |
| `sendToUsersWithPermission($businessUserId, $permission, ...)` | Send to users with a specific role permission under a business owner |
| `sendToTokens($tokens, ...)` | Low-level method that sends to FCM |

**Authentication flow:**

1. Reads the Firebase service account JSON from `storage/app/firebase-credentials.json`
2. Builds a JWT signed with RS256
3. Exchanges the JWT for an OAuth2 access token at `oauth2.googleapis.com/token`
4. Sends `POST https://fcm.googleapis.com/v1/projects/{projectId}/messages:send` with a Bearer token

**Invalid token cleanup:** When Firebase returns a 404 for a token, it is automatically deleted from the `device_tokens` table.

### Configuration

**Environment variables** (`.env`):

```env
FCM_PROJECT_ID=your-firebase-project-id
FCM_CREDENTIALS=/path/to/storage/app/firebase-credentials.json
NOTIFICATION_LARGE_SALE_THRESHOLD=10000
NOTIFICATION_LARGE_REFUND_THRESHOLD=5000
NOTIFICATION_DAILY_SUMMARY_TIME=20:00
```

---

## Notification Types

### Large Sale

- **Trigger**: Sale total >= `NOTIFICATION_LARGE_SALE_THRESHOLD` (default P10,000)
- **Source**: `SaleController` after a sale is completed
- **Permission**: `sls`
- **Data**: `{ type: "large_sale", id: "{saleId}" }`

### Refund Alert

- **Trigger**: Refund total >= `NOTIFICATION_LARGE_REFUND_THRESHOLD` (default P5,000)
- **Source**: `SaleController` after a refund is processed
- **Permission**: `sls`
- **Data**: `{ type: "refund_alert", id: "{saleId}" }`

### Daily Sales Summary

- **Trigger**: Scheduled daily at `NOTIFICATION_DAILY_SUMMARY_TIME` (default 20:00)
- **Source**: `SendDailySalesSummary` artisan command, scheduled in `routes/console.php`
- **Permission**: `sls`
- **Data**: `{ type: "daily_sales_summary" }`
- **Body**: Includes total sales amount, transaction count, and top product

### Higher Access Request

- **Trigger**: POS employee requests elevated permission (discount, refund, delete items, cash out, credit sale)
- **Source**: `HigherAccessController`
- **Permission**: Matches the permission being requested (`discounts`, `rfnd`, `delete_items`, `csh_out`, `crdt_sale`)
- **Data**: `{ type: "higher_access_request", id: "{requestId}" }` (UUID)

### Late Clock-In

- **Trigger**: Employee clocks in after their scheduled start time
- **Source**: `AttendanceRecord` model (boot method)
- **Permission**: `attndnc`
- **Data**: `{ type: "late_clockin", id: "{attendanceRecordId}" }`

### Reorder Alert

- **Trigger**: Demand forecast detects critical/high priority reorder items
- **Source**: `DemandForecastService`
- **Permission**: `invntry`
- **Data**: `{ type: "reorder_alert" }`

---

## Flutter App (apex_dashboard)

### Key Files

| Component | Path |
|---|---|
| Push Notification Service | `lib/services/push_notification_service.dart` |
| Notifications Repository | `lib/services/notifications/notifications_repository.dart` |
| Device Token Controller | `lib/controllers/device_token_controller.dart` |
| Notification Model | `lib/models/notification_model.dart` |
| Notifications Page | `lib/responsive/pages/notifications/notifications_page.dart` |
| Notification Badge | `lib/components/notifications/notification_badge.dart` |
| Firebase Options | `lib/config/firebase_options.dart` |

### Dependencies

- `firebase_core` / `firebase_messaging` — FCM integration
- `flutter_local_notifications` — foreground notification display
- `hive` — local storage for notification history

### Initialization Flow

1. `main.dart` initializes Firebase with a 5-second timeout
2. After login, `PushNotificationService().init()` is called
3. The service requests notification permissions (alert, badge, sound)
4. On iOS, waits for the APNS token (up to 5 seconds)
5. Gets the FCM token and registers it with the backend
6. Sets up listeners for foreground messages, background taps, and token refresh

### Message Handling

| App State | Handler | Behavior |
|---|---|---|
| **Foreground** | `FirebaseMessaging.onMessage` | Saves to Hive, shows local notification via `flutter_local_notifications` |
| **Background** | `onMessageOpenedApp` | Saves to Hive, navigates to the relevant page |
| **Terminated** | `getInitialMessage()` | Checked on init, navigates to the relevant page |

### Notification-to-Page Mapping

| Notification Type | Destination Page |
|---|---|
| `ecommerce_order` | Ecommerce Order Index / Show |
| `po_approval` | Purchase Order Index / Show |
| `margin_alert` | Profit Margins |
| `low_stock` | Inventory Report |
| `daily_sales_summary` | Sales Summary |
| `reorder_alert` | Demand Forecast |
| `late_clockin` | Attendance Index |
| `large_sale` | Sales Summary |
| `refund_alert` | Refund Report |
| `higher_access_request` | Pending Requests |

### Local Storage

- Notifications are stored in Hive with a max of **50 items**
- Duplicate notifications are skipped (by message ID)
- `ValueNotifier<int> unreadCount` provides reactive badge updates

### Logout Cleanup

1. Calls `DELETE /device-tokens` to unregister from the backend
2. Calls `FirebaseMessaging.deleteToken()` to remove from Firebase
3. Resets internal state

---

## Testing

### Running Notification Tests

```bash
vendor/bin/sail artisan test tests/Feature/Notifications/
```

### Test Files

| Test | File |
|---|---|
| Large Sale | `tests/Feature/Notifications/LargeSaleNotificationTest.php` |
| Higher Access Request | `tests/Feature/Notifications/HigherAccessRequestNotificationTest.php` |
| Late Clock-In | `tests/Feature/Notifications/LateClockInNotificationTest.php` |
| Reorder Alert | `tests/Feature/Notifications/ReorderAlertNotificationTest.php` |
| Daily Sales Summary | `tests/Feature/Notifications/DailySalesSummaryTest.php` |

### Sending a Test Notification

```bash
vendor/bin/sail artisan notification:test --user=1 --type=large_sale --title="Test" --body="Hello"
```

Options:

| Flag | Description | Default |
|---|---|---|
| `--user` | User ID to send to (all if omitted) | all |
| `--title` | Notification title | "Test Notification" |
| `--body` | Notification body | "This is a test notification from Apex Backend" |
| `--type` | Notification type for deep linking | "test" |
| `--id` | Target entity ID for deep linking | null |

Supported types: `ecommerce_order`, `po_approval`, `margin_alert`, `low_stock`, `daily_sales_summary`, `reorder_alert`, `late_clockin`, `large_sale`, `refund_alert`, `higher_access_request`, `test`

---

## Data Flow Diagram

```
┌─────────────┐     POST /device-tokens     ┌─────────────────┐
│   Flutter    │ ──────────────────────────►  │  Laravel Backend │
│   Dashboard  │                              │                 │
│             │◄──── FCM Push ──────────────  │  FcmService     │
└──────┬──────┘                              └────────┬────────┘
       │                                              │
       │  1. Get FCM token                            │  3. Send message
       │                                              │     (JWT auth)
       ▼                                              ▼
┌──────────────────────────────────────────────────────────────┐
│                    Firebase Cloud Messaging                   │
│                    (Google Infrastructure)                     │
└──────────────────────────────────────────────────────────────┘
```

1. The Flutter app registers its device token with both Firebase and the backend
2. When a notification-worthy event occurs, the backend authenticates with Firebase using a service account JWT
3. Firebase delivers the message to the registered device(s)
4. The Flutter app handles display and navigation based on the notification type
