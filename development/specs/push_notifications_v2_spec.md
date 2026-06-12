# Push Notifications v2 - Dashboard Implementation Spec

## Overview

The backend now supports **5 new push notification types** in addition to the existing 4. This spec covers what the Apex Dashboard (Flutter) needs to implement to fully support them.

### Existing Types (already handled)
| Type | Icon | Navigation |
|------|------|-----------|
| `ecommerce_order` | `shopping_bag_rounded` | `EcommerceOrderShow` / `EcommerceOrderIndex` |
| `po_approval` | `approval_rounded` | `PurchaseOrderShow` / `PurchaseOrderIndex` |
| `margin_alert` | `trending_down_rounded` | `ProfitMarginsPage` |
| `low_stock` | `inventory_rounded` | `InventoryReportPage` |

### New Types (to implement)
| Type | Backend Trigger | Priority |
|------|-----------------|----------|
| `daily_sales_summary` | Scheduled at 20:00 daily | Low |
| `reorder_alert` | After demand forecast finds critical/high items | Medium |
| `late_clockin` | Employee clocks in late | Medium |
| `large_sale` | Sale total >= P10,000 (configurable) | Low |
| `refund_alert` | Refund total >= P5,000 (configurable) | Medium |
| `higher_access_request` | POS requests elevated permission | High |

---

## Notification Data Payloads

Each FCM message includes a `data` map. The `type` field determines navigation, and `id` is the target entity for deep linking.

### 1. Daily Sales Summary
```json
{
  "type": "daily_sales_summary"
}
```
- **Title:** "Daily Sales Summary"
- **Body:** "Today: P5,000.00 from 42 transaction(s). Top: Rice"
- **No `id` field** (summary, not entity-specific)

### 2. Reorder Alert
```json
{
  "type": "reorder_alert"
}
```
- **Title:** "Reorder Alert"
- **Body:** "3 critical reorder suggestion(s) need attention"
- **No `id` field** (navigates to suggestions list)

### 3. Late Clock-in
```json
{
  "type": "late_clockin",
  "id": "42"
}
```
- **Title:** "Late Clock-in"
- **Body:** "Juan dela Cruz clocked in 15 minutes late at Main Store"
- **`id`:** Attendance record ID

### 4. Large Sale
```json
{
  "type": "large_sale",
  "id": "1234"
}
```
- **Title:** "Large Sale"
- **Body:** "Large sale: P15,000.00 at Main Store"
- **`id`:** Sale ID

### 5. Refund Alert
```json
{
  "type": "refund_alert",
  "id": "1235"
}
```
- **Title:** "Refund Alert"
- **Body:** "Refund alert: P7,000.00 at Main Store"
- **`id`:** Sale ID (of the refund)

### 6. Higher Access Request
```json
{
  "type": "higher_access_request",
  "id": "550e8400-e29b-41d4-a716-446655440000"
}
```
- **Title:** "Access Request"
- **Body:** "Juan requests discounts access at Main Store"
- **`id`:** Request UUID (string, not numeric)

---

## Changes Required

### File 1: `lib/services/push_notification_service.dart`

Update `getPageForNotificationType()` to handle the new types:

```dart
static Widget? getPageForNotificationType(String? type, {String? targetId}) {
  final id = targetId != null ? num.tryParse(targetId) : null;

  switch (type) {
    // Existing types
    case 'ecommerce_order':
      if (id != null) {
        return EcommerceOrderShow(order: EcommerceOrderModel(id: id));
      }
      return const EcommerceOrderIndex();
    case 'po_approval':
      if (id != null) {
        return PurchaseOrderShow(order: PurchaseModel(id: id));
      }
      return const PurchaseOrderIndex(initialApprovalStatusFilter: 1);
    case 'margin_alert':
      return const ProfitMarginsPage();
    case 'low_stock':
      return const InventoryReportPage();

    // New types
    case 'daily_sales_summary':
      return const SalesSummaryPage();
    case 'reorder_alert':
      return const DemandForecastPage();
    case 'late_clockin':
      return const AttendanceIndexPage();
    case 'large_sale':
      return const SalesSummaryPage();
    case 'refund_alert':
      return const RefundReportPage();
    case 'higher_access_request':
      return const PendingRequestsPage();
    default:
      return null;
  }
}
```

**New imports needed:**
```dart
import 'package:apex_dashboard/responsive/pages/reports/sales_summary_page.dart';
import 'package:apex_dashboard/responsive/pages/reports/refund_report.dart';
import 'package:apex_dashboard/responsive/pages/forecast/demand_forecast_page.dart';
import 'package:apex_dashboard/responsive/pages/attendance/attendance_index.dart';
import 'package:apex_dashboard/components/higher_access/pending_requests_page.dart';
```

---

### File 2: `lib/responsive/pages/notifications/notifications_page.dart`

#### Update `_iconForType()`
```dart
IconData _iconForType(String? type) {
  switch (type) {
    case 'ecommerce_order':
      return Icons.shopping_bag_rounded;
    case 'po_approval':
      return Icons.approval_rounded;
    case 'margin_alert':
      return Icons.trending_down_rounded;
    case 'low_stock':
      return Icons.inventory_rounded;
    // New types
    case 'daily_sales_summary':
      return Icons.summarize_rounded;
    case 'reorder_alert':
      return Icons.production_quantity_limits_rounded;
    case 'late_clockin':
      return Icons.schedule_rounded;
    case 'large_sale':
      return Icons.attach_money_rounded;
    case 'refund_alert':
      return Icons.money_off_rounded;
    case 'higher_access_request':
      return Icons.admin_panel_settings_rounded;
    default:
      return Icons.notifications_rounded;
  }
}
```

#### Update `_colorForType()`
```dart
Color _colorForType(String? type) {
  switch (type) {
    case 'ecommerce_order':
      return AppColor.primary;
    case 'po_approval':
      return AppColor.warning;
    case 'margin_alert':
      return AppColor.danger;
    case 'low_stock':
      return AppColor.orange;
    // New types
    case 'daily_sales_summary':
      return AppColor.success;
    case 'reorder_alert':
      return AppColor.orange;
    case 'late_clockin':
      return AppColor.warning;
    case 'large_sale':
      return AppColor.success;
    case 'refund_alert':
      return AppColor.danger;
    case 'higher_access_request':
      return AppColor.primary;
    default:
      return AppColor.info;
  }
}
```

---

## Who Receives Each Notification

Notifications are scoped per business (`user_id`) and filtered by role permission:

| Type | Role Permission | Scope |
|------|----------------|-------|
| `daily_sales_summary` | `sls` | Per business, users with sales access |
| `reorder_alert` | `invntry` | Per business, users with inventory access |
| `late_clockin` | `attndnc` | Per business, users with attendance access |
| `large_sale` | `sls` | Per business, users with sales access |
| `refund_alert` | `sls` | Per business, users with sales access |
| `higher_access_request` | `discounts` / `rfnd` / `delete_items` | Per business, matches the permission being requested |

The backend uses `FcmService::sendToUsersWithPermission($businessUserId, $permission, ...)` which:
1. Finds all roles for the business with the permission enabled
2. Finds all users with those roles
3. Sends to their registered device tokens

---

## Backend Configuration

Configurable via environment variables (defaults in `config/notifications.php`):

| Config Key | Env Variable | Default |
|-----------|-------------|---------|
| `notifications.large_sale_threshold` | `NOTIFICATION_LARGE_SALE_THRESHOLD` | 10000 |
| `notifications.large_refund_threshold` | `NOTIFICATION_LARGE_REFUND_THRESHOLD` | 5000 |
| `notifications.daily_summary_time` | `NOTIFICATION_DAILY_SUMMARY_TIME` | 20:00 |

---

## Backend Source Locations

| Notification | File | Method/Line |
|-------------|------|-------------|
| Daily Sales Summary | `app/Console/Commands/SendDailySalesSummary.php` | `handle()` |
| Reorder Alert | `app/Services/DemandForecastService.php` | `generateReorderSuggestions()` |
| Late Clock-in | `app/Models/Employees/AttendanceRecord.php` | `notifyLateClockIn()` |
| Large Sale / Refund | `app/Http/Controllers/API/v1/pos/SaleController.php` | `notifyLargeSaleOrRefund()` |
| Higher Access Request | `app/Http/Controllers/API/v1/pos/HigherAccessController.php` | `notifyHigherAccessRequest()` |
| Shared helper | `app/Services/FcmService.php` | `sendToUsersWithPermission()` |

Schedule entry in `routes/console.php`:
```
Schedule::command('notification:daily-sales-summary')
    ->dailyAt(config('notifications.daily_summary_time', '20:00'));
```

---

## Testing

### Backend
Test files in `tests/Feature/Notifications/`:
- `DailySalesSummaryTest.php` (4 tests)
- `ReorderAlertNotificationTest.php` (2 tests)
- `LateClockInNotificationTest.php` (3 tests)
- `LargeSaleNotificationTest.php` (4 tests)
- `HigherAccessRequestNotificationTest.php` (4 tests)

Run: `vendor/bin/sail artisan test tests/Feature/Notifications/`

### Manual Testing (Backend)
Use the test command to send any notification type to a device:
```bash
vendor/bin/sail artisan notification:test --type=daily_sales_summary
vendor/bin/sail artisan notification:test --type=reorder_alert
vendor/bin/sail artisan notification:test --type=late_clockin
vendor/bin/sail artisan notification:test --type=large_sale
vendor/bin/sail artisan notification:test --type=refund_alert
vendor/bin/sail artisan notification:test --type=higher_access_request
```

### Dashboard (Flutter)
After implementing the changes:
1. Send a test notification for each type using the artisan command above
2. Verify the correct icon and color appear in the notifications page
3. Tap each notification and verify it navigates to the correct page
4. Verify background tap navigation works (kill app, receive notification, tap)

---

## Implementation Checklist

- [ ] Update `push_notification_service.dart` — add 6 new cases to `getPageForNotificationType()`, add imports
- [ ] Update `notifications_page.dart` — add 6 new cases to `_iconForType()` and `_colorForType()`
- [ ] Test each type with `notification:test` command
- [ ] Verify foreground display, background tap, and terminated-state tap
