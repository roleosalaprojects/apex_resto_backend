# Development Session Log - January 27, 2026

## Summary
This session implemented the Employee Schedules and Late Tracking feature for the web admin interface, including schedule management, late detection, and attendance view enhancements.

---

## 1. Employee Schedule Management

### Overview
Added weekly schedule management for employees allowing admins to set start times for each day of the week or mark days as rest days.

### Files Created
- `app/Http/Controllers/Admin/Employees/EmployeeScheduleController.php`
  - `index()` - List employees with their weekly schedules
  - `edit($user)` - Show form with 7 days for one employee
  - `update($user)` - Save all 7 days at once using updateOrCreate
  - `table()` - AJAX endpoint for filtered table with search

- `app/Http/Requests/Admin/EmployeeSchedule/UpdateRequest.php`
  - Validates 7-day schedule array
  - Time format validation (H:i)
  - Rest day checkbox handling

### Views Created
- `resources/views/admin/schedules/index.blade.php` - Employee list with search filter
- `resources/views/admin/schedules/edit.blade.php` - Edit form with grace period info
- `resources/views/admin/schedules/_form.blade.php` - 7-day schedule table with time inputs and rest day checkboxes
- `resources/views/admin/schedules/table.blade.php` - AJAX table showing weekly schedule per employee

### Routes Added
```
GET    /admin/schedules           - List employees with schedules
GET    /admin/schedules/{user}/edit - Edit employee schedule
PUT    /admin/schedules/{user}    - Update employee schedule
GET    /admin/schedules/table     - AJAX table data with search
```

---

## 2. Late Tracking in Attendance

### Overview
Added late tracking columns to attendance records and updated views to display late information.

### Database Changes
- `is_late` (boolean) - Whether employee was late
- `late_minutes` (unsigned integer) - How many minutes late

### Files Modified

**Attendance Index (`resources/views/admin/attendance/index.blade.php`):**
- Added "Late" option to status filter dropdown

**Attendance Table (`resources/views/admin/attendance/table.blade.php`):**
- Added "Late" column header
- Added late badge showing minutes (warning color) when late
- Updated colspan for empty state (8 → 9)

**Attendance Summary (`resources/views/admin/attendance/summary.blade.php`):**
- Added "Late Days" column with warning badge
- Added "Late Minutes" column
- Added totals for late days and late minutes in footer

**Attendance Controller (`app/Http/Controllers/Admin/Employees/AttendanceController.php`):**
- Updated `table()` method to handle `status=late` filter
- Filters by `is_late=true` when late status selected

---

## 3. Mobile API Endpoints

### Files Created
- `app/Http/Controllers/API/v1/mobile/EmployeeScheduleController.php`
  - `index()` - Get authenticated user's weekly schedule

- `app/Http/Controllers/API/v1/mobile/AttendanceController.php`
  - `clockIn()` - Clock in with automatic late calculation
  - `clockOut()` - Clock out and calculate hours rendered
  - `today()` - Get today's attendance record
  - `history()` - Get attendance history with pagination

### Routes Added (API)
```
GET    /api/v1/mobile/schedules              - Get user's schedule
POST   /api/v1/mobile/attendance/clock-in    - Clock in
POST   /api/v1/mobile/attendance/clock-out   - Clock out
GET    /api/v1/mobile/attendance/today       - Today's record
GET    /api/v1/mobile/attendance/history     - Attendance history
```

---

## 4. Configuration

### Files Created
- `config/attendance.php`
  - `grace_period` setting (default: 15 minutes)
  - Used by late calculation logic

---

## 5. Sidebar Menu Update

### Files Modified
- `resources/views/layout/layout/partials/sidebar/_menu.blade.php`
  - Added "Schedules" link under User Management
  - Located after "Attendance" link
  - Active state on `schedules.*` routes

---

## 6. Factory Updates

### Files Created
- `database/factories/EmployeeScheduleFactory.php`
  - `forDay(int $dayOfWeek)` - Set specific day
  - `startingAt(string $time)` - Set start time
  - `restDay()` - Mark as rest day (null start_time)

### Files Modified
- `database/factories/AttendanceRecordFactory.php`
  - Added `late(int $minutes = 30)` state
  - Added `onTime()` state

---

## 7. Model Updates

### Files Modified
- `app/Models/User.php`
  - Added `employeeSchedules()` hasMany relationship
  - Added `getScheduleForDay(int $dayOfWeek)` method
  - Added `calculateLate(Carbon $clockInTime)` method

- `app/Models/AttendanceRecord.php`
  - Added `is_late` and `late_minutes` to fillable
  - Added casts for `is_late` (boolean) and `late_minutes` (integer)

- `app/Http/Resources/AttendanceRecordResource.php`
  - Added `is_late` and `late_minutes` to JSON output

---

## Test Coverage

### Files Created
- `tests/Feature/Admin/EmployeeScheduleTest.php` - 8 tests

All tests passing:
- `test_can_view_schedules_index`
- `test_can_view_schedule_edit_form`
- `test_can_update_employee_schedule`
- `test_schedule_table_shows_employee_schedules`
- `test_schedule_table_can_filter_by_search`
- `test_update_schedule_validates_time_format`
- `test_existing_schedule_is_updated_not_duplicated`
- `test_user_without_permission_cannot_access_schedules`

---

## 8. Role Permissions for Attendance, Banking & Expenses

### Overview
Added dedicated role permissions for attendance, banking, and expenses modules. Previously attendance used `emplys` permissions; now it has its own `attndnc` permission set. Banking (`bnkng`) and expenses (`expnss`) permissions were also added as part of the same migration.

### Migration
- `database/migrations/2026_01_27_100000_add_attendance_permissions_to_roles_table.php`
  - Adds `attndnc`, `attndnc_read`, `attndnc_create`, `attndnc_update`, `attndnc_delete`, `attndnc_schedules`
  - Adds `bnkng`, `bnkng_read`, `bnkng_create`, `bnkng_update`, `bnkng_delete`
  - Adds `expnss`, `expnss_read`, `expnss_create`, `expnss_update`, `expnss_delete`
  - Fixed `down()` to handle missing columns gracefully on rollback

### Files Modified
- `app/Http/Controllers/Admin/Employees/RoleController.php`
  - Added all 17 new permission fields to `store()` and `update()`

- `resources/views/admin/roles/_fields.blade.php`
  - Added "Attendance Management" section (Read, Create, Update, Delete, Manage Schedules)
  - Added "Accounting" section with Banking (CRUD) and Expenses (CRUD)

- `app/Http/Controllers/Admin/Employees/AttendanceController.php`
  - Switched from `emplys` to `attndnc` permissions (`attndnc`, `attndnc_read`, `attndnc_create`, `attndnc_update`, `attndnc_delete`)

- `app/Http/Controllers/Admin/Employees/EmployeeScheduleController.php`
  - Switched from `emplys`/`emplys_update` to `attndnc_schedules` permission

- `database/factories/RoleFactory.php`
  - Added all 17 permissions to `admin()` state

- `app/Models/Role.php` - Already had all fields in fillable
- `app/Http/Resources/RoleResource.php` - Added new permission fields to JSON output
- `tests/Feature/Admin/EmployeeScheduleTest.php` - Updated to use `attndnc_schedules` permission

---

## Commits
1. `d5cc134` - Add employee schedules and late tracking feature
2. `21f67c9` - Update CHANGELOG with employee schedules and late tracking feature
3. `cb8f556` - Add session log for January 27, 2026
4. `2ef61c1` - Add attendance, banking, and expenses role permissions

---

## Files Summary

| Category | Count |
|----------|-------|
| Controllers Created | 3 |
| Controllers Modified | 3 (AttendanceController, EmployeeScheduleController, RoleController) |
| Views Created | 4 |
| Views Modified | 2 (attendance index, roles _fields) |
| Migrations Created | 3 |
| Config Created | 1 |
| Factories Created/Modified | 3 |
| Models Modified | 3 |
| Tests Created/Modified | 1 (8 test methods) |

---

## Next Steps / Notes
- Employee schedules accessible from sidebar under User Management
- Late tracking automatically calculated on mobile clock-in
- Grace period configurable in `config/attendance.php`
- Attendance, Banking, and Expenses now have dedicated role permissions
- All changes merged to `main` branch
