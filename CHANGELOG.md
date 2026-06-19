# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [Restaurant POS] - 2026-06-19

### Added — Split bill (Phase 5)
- Per-line settlement: `order_lines.sales_id` links each line to the Sale
  that settled it (`NULL` = unsettled), so one order can produce multiple
  official receipts.
- `RestaurantOrderService::splitSettle()` bills a chosen subset of an
  order's lines onto its own Sale and leaves the rest open; the order
  completes and the table frees only once every non-voided line is
  settled. A shared `settleLines()` helper backs both `splitSettle()` and
  the existing full `settle()`, which now bills whatever remains unsettled
  — so a split bill can be finished with either call.
- `POST /v1/restaurant-orders/{order}/split-settle` (`line_ids[]` +
  payment); returns `fully_settled` and `order_status`. Selecting an
  already-settled or voided line is rejected with `422`.
- `SaleCreationData::fromRestaurantOrder` now bills an explicit set of
  lines rather than "all non-voided", with no change to the full-settle
  total/VAT behaviour.

## [Restaurant POS] - 2026-06-16

Forked from apex_backend with a fresh history and specialised into a
restaurant POS backend. Backend + admin dashboard + REST APIs only;
Flutter waiter/KDS/cashier frontends are a later project.

### Added — Composite items (Phase 1)
- `is_composite` / `cost_override` / `uom_label` on items and an
  `item_components` recipe table.
- `CompositeItemService` (recipe sync, cost recalculation, BFS cycle
  guard, active-component check) and an `ItemCostObserver` +
  `RecalculateCompositeCostsJob` that cascade ingredient cost changes up
  the recipe graph.
- Selling a composite explodes the recipe and deducts each component's
  stock (`UpdateItemStocksJob`); refunds restore symmetrically.
- Admin item form recipe repeater with live computed cost; POS item API
  exposes `is_composite` + components.

### Added — Restaurant operations (Phase 2)
- Tables, kitchen stations and reservations (models, admin CRUD +
  DataTables, FullCalendar reservation feed, `rstrnt` role permissions).
- Order / order-line extensions for dine-in/take-out/delivery, pax,
  rounds, per-line kitchen routing and KDS line status.
- `RestaurantOrderService` (open/rounds/transfer/void-line/settle/cancel)
  settling through the shared `SaleCreationService`; `KdsService`
  (station queue + line/order bump); `KitchenRoutingService`.
- POS APIs: `/v1/restaurant-orders`, `/v1/kds/*`, `/v1/tables`,
  `/v1/reservations`.

### Added — BIR Annex F compliance (Phase 3)
- Gapless per-terminal document series via `DocumentNumberService`
  (SI / void / return / transaction numbers under a row lock); training
  transactions use a separate counter and stay off the official series.
- Card (6) and Gift Certificate (7) payment types.
- Same-day void endpoint (issues void number, restores stock) and
  receipt reprint tracking.
- Training-mode exclusion across X/Z readings, `ReportService` and
  dashboards; X/Z readings gain per-tender breakdown, void/return ranges,
  `z_counter` and transaction numbers.
- `DiscountAllocationService` SC/PWD group discount (RMC 38-2012) with
  server-side ±0.01 validation of client discount math.
- `Auditable` on Sale and Order.

### Added — BIR reports, seeder, polish (Phase 4)
- `BirReportService` + admin Annex F reports (sales summary, voided
  transactions, discount sales book, adjustments, daily sales by VAT
  class) with audit-logged CSV export.
- `RestaurantDemoSeeder` (env-gated) — demo tenant, store, two terminals,
  three stations, fine-unit ingredients, Iced Latte & Sisig composites,
  twelve tables, sample reservations.

### Changed
- Rebranded identity (composer/package name, `.env.example`, README,
  Postman collection) with non-colliding ports so apex_backend and
  apex_resto_backend can run side by side.

---

## [Unreleased] - 2026-05-11

### Security (BREAKING)

#### Public registration endpoints removed
Closed four publicly-reachable account-creation paths that anyone on the
internet could use to mint privileged credentials:

- `POST /superadmin/register` — created `admins` rows with full superadmin
  privileges over BIR readings, receipts, and tenant-wide adjustments.
- `POST /admin/register` (via `Auth::routes()`) — created `users` rows
  with a valid Passport `auth:api` token and active admin session.
- `POST /api/v1/register` — minted `auth:api` tokens to anonymous
  callers, unlocking customer search, credit-balance read/write, sale
  refunds, and cross-tenant data exposure.
- `POST /api/v1/image-updater/register` — same `auth:api` token issuance
  plus arbitrary product-image SVG uploads (stored XSS surface).

Replaced with two artisan commands for first-time setup. Subsequent
users are provisioned through the Employees module in the admin UI.

```bash
vendor/bin/sail artisan apex:create-superadmin \
    --name="Owner" --email=owner@example.com --password=changemenow

vendor/bin/sail artisan apex:create-admin \
    --name="Owner" --email=owner@example.com --password=changemenow
```

Both commands accept `--force` to reset the password on an existing
account (role and tenant scope are preserved).

#### Public maintenance endpoint removed
`GET /fix/database` (unauthenticated `REPAIR TABLE` on six tables) is
gone. Use `mysqlcheck --repair` from the host if the underlying issue
ever returns.

### Files removed
- `app/Http/Controllers/SuperAdmin/RegisterController.php`
- `app/Http/Controllers/API/v1/pos/RegisterController.php`
- `app/Http/Controllers/Auth/RegisterController.php`
- `resources/views/auth/register.blade.php`
- `resources/views/superadmin/register/index.blade.php`

### Files added
- `app/Console/Commands/ApexCreateSuperAdmin.php`
- `app/Console/Commands/ApexCreateAdmin.php`
- `tests/Feature/Console/ApexCreateSuperAdminTest.php`
- `tests/Feature/Console/ApexCreateAdminTest.php`

### Files modified
- `routes/web.php` — removed `/fix/database`
- `routes/admin.php` — `Auth::routes(['register' => false])`
- `routes/api/pos.php` — removed `POST /api/v1/register`
- `routes/api.php` — removed `POST /api/v1/image-updater/register`
- `routes/superadmin.php` — removed superadmin register routes
- `app/Http/Controllers/API/AuthController.php` — removed `register()` method
- `resources/views/auth/login.blade.php` — removed "register" link
- `app/Models/Employees/Role.php` — extracted `Role::fullAccessFlags()` shared between the factory and `apex:create-admin`
- `database/factories/Employees/RoleFactory.php` — `admin()` state delegates to `Role::fullAccessFlags()`
- `tests/Feature/Auth/RegisterControllerTest.php` — flipped to assert each removed endpoint now returns 404/405

### Deployment / first-time setup
Production deployments should run one of these once per environment:

```bash
php artisan apex:create-superadmin   # /superadmin panel access
php artisan apex:create-admin        # first /admin tenant-owner account
```

Existing installations are unaffected — no migrations.

---

## [Unreleased] - 2026-02-16

### Added

#### AI Item Insights — Top 100 Sellable Items Per Day
Composite sellability scoring (0-100) ranks every active item with sales history for a given date. Scores combine 7 weighted factors: volume (30), trend (20), margin (15), consistency (10), stock readiness (10), seasonal/holiday (10), and weather (5). Bulk SQL queries keep it performant, and a single batched Ollama call generates per-item AI insights.

**Services (1 new, 1 modified):**
- `app/Services/ItemInsightsService.php` - Core scoring engine with 4 bulk queries, composite scoring, holiday/payday/weather factors
- `app/Services/OllamaService.php` - Added `generateItemInsights()` for batched per-item insight generation

**Model + Migration (2 files):**
- `app/Models/ItemInsight.php` - Model with item/store relationships, JSON casts for score_breakdown and factors
- `database/migrations/2026_02_16_115532_create_item_insights_table.php` - Table with unique composite index

**Controllers (2 files):**
- `app/Http/Controllers/Admin/ItemInsightsController.php` - Admin dashboard with DataTable, summary stats
- `app/Http/Controllers/API/v1/Analytics/ItemInsightsController.php` - API endpoint for dashboard integration

**Views (1 file):**
- `resources/views/admin/insights/index.blade.php` - Stats cards, DataTable with score progress bars, factor badges, AI insights

**Tests (1 file):**
- `tests/Feature/Services/ItemInsightsServiceTest.php` - 15 tests covering ranking, scoring factors, AI batching, scoping, caching

**Routes:**
- Admin: `GET /admin/insights/`, `GET /admin/insights/data`, `GET /admin/insights/summary`
- API: `GET /api/v1/analytics/item-insights`

**Sidebar:**
- Added "AI Analytics" group with Demand Forecasting and AI Item Insights links

#### Claude (Anthropic API) Integration for AI Insights
Added Claude as primary AI provider for all AI-generated insights (sales forecasts, reorder reasons, pattern detection, per-item insights). Ollama remains as automatic fallback when Claude is unavailable.

**Services (2 new, 4 modified):**
- `app/Services/AnthropicService.php` - Claude Messages API client with same method signatures as OllamaService
- `app/Services/AiService.php` - Unified AI wrapper: tries Anthropic first, falls back to Ollama
- `app/Services/DemandForecastService.php` - Uses AiService instead of OllamaService
- `app/Services/ItemInsightsService.php` - Uses AiService instead of OllamaService

**Configuration:**
- `config/services.php` - Added `anthropic` config (api_key, model, timeout)
- Env: `ANTHROPIC_API_KEY`, `ANTHROPIC_MODEL` (default: claude-sonnet-4-5-20250929), `ANTHROPIC_TIMEOUT`

**Controllers (3 modified):**
- Admin ForecastController, ItemInsightsController - Uses AiService, shows active provider name
- API ForecastController - Uses AiService, `ai_available`/`ai_provider` replace `ollama_available`

**Routes:**
- `GET /admin/forecast/ai-status` replaces `/ollama-status`
- `GET /api/.../ai-status` replaces `/ollama-status`

---

#### Peak Hours Analysis
Heatmap-based traffic analysis showing average sales and receipts per hour × day-of-week, normalized across date occurrences to accurately gauge expected store traffic.

**Services (1 file):**
- `app/Services/PeakHoursAnalysisService.php` - Heatmap aggregation with `CONVERT_TZ` for timezone-aware hour grouping, avg sales/receipts per distinct date

**Controllers (2 files):**
- `app/Http/Controllers/Admin/Reports/PeakHoursController.php` - Admin page with heatmap, hourly bar chart, peak/slow tables
- `app/Http/Controllers/API/v1/Analytics/PeakHoursController.php` - API endpoints for dashboard

**Views (1 file):**
- `resources/views/admin/reports/peak_hours.blade.php` - ApexCharts heatmap + bar chart, Metronic theme integration

**Tests (1 file):**
- `tests/Feature/Services/PeakHoursAnalysisServiceTest.php` - 10 tests including averaging normalization

**API Endpoints:**
- `GET /api/v1/analytics/peak-hours` - Heatmap data with avg sales/receipts
- `GET /api/v1/analytics/hourly-breakdown` - Single-date hourly breakdown

---

#### Profit Margin Tracking
Item-level margin analysis with period comparison, trend tracking, and automatic alerts for margin drops exceeding 5%.

**Services (1 file):**
- `app/Services/ProfitAnalysisService.php` - Margin calculation, period comparison, trend data, alert detection

**Controllers (2 files):**
- `app/Http/Controllers/Admin/Reports/ProfitMarginController.php` - Admin page with margin chart and DataTable
- `app/Http/Controllers/API/v1/Analytics/ProfitMarginController.php` - API endpoints

**Views (1 file):**
- `resources/views/admin/reports/profit_margins.blade.php` - ApexCharts mixed bar/line chart, sortable DataTable with export

**Tests (1 file):**
- `tests/Feature/Services/ProfitAnalysisServiceTest.php` - 9 tests

**API Endpoints:**
- `GET /api/v1/analytics/profit-margins` - Margin data with period comparison
- `GET /api/v1/analytics/profit-margins/{item_id}/trend` - Item margin trend over time
- `GET /api/v1/analytics/margin-alerts` - Items with margin drops > 5%

---

#### Scheduled Report Generation
Automated daily and weekly sales report emails with summary, top items, peak hours, and margin alerts. Configurable recipients via admin panel.

**Services (1 file):**
- `app/Services/ReportService.php` - Sales summary aggregation, period comparison, report data assembly

**Mail (2 files):**
- `app/Mail/DailySalesReport.php` - Daily report mailable (Apex branding)
- `app/Mail/WeeklySalesReport.php` - Weekly report mailable (Apex branding)

**Email Templates (2 files):**
- `resources/views/emails/daily-sales-report.blade.php` - Daily summary with top items, margin alerts
- `resources/views/emails/weekly-sales-report.blade.php` - Weekly comparison with peak hours, margin alerts

**Migrations (1 file):**
- `database/migrations/2026_02_15_183302_create_report_recipients_table.php` - Report recipients table

**Model & Factory (2 files):**
- `app/Models/ReportRecipient.php` - Report recipient model
- `database/factories/ReportRecipientFactory.php` - Factory with states

**Commands (1 file):**
- `app/Console/Commands/ReportGenerate.php` - `report:generate --type=daily|weekly` with `sendNow()`

**Controllers (2 files):**
- `app/Http/Controllers/Admin/Reports/ScheduledReportController.php` - Admin CRUD for recipients
- `app/Http/Controllers/API/v1/Analytics/ReportController.php` - API endpoints

**Views (1 file):**
- `resources/views/admin/reports/scheduled/index.blade.php` - Manage report recipients

**Tests (1 file):**
- `tests/Feature/Services/ReportServiceTest.php` - 13 tests

**API Endpoints:**
- `GET /api/v1/reports/sales-summary` - Sales summary by period
- `GET /api/v1/reports/recipients` - List report recipients
- `POST /api/v1/reports/recipients` - Add recipient
- `DELETE /api/v1/reports/recipients/{id}` - Remove recipient

---

#### Mail Branding & Infrastructure
- Published Laravel mail views with Apex branding (header, footer, sign-off)
- Integrated MailerSend SMTP transport (`mailersend/laravel-driver`)
- `resources/views/vendor/mail/` - Customized mail templates: "Apex" header, "© Apex. All rights reserved." footer

### Changed

**Sidebar Updated (2 files):**
- `resources/views/layout/layout/partials/sidebar/_menu.blade.php` - Added Peak Hours, Profit Margins, Scheduled Reports
- `resources/views/admin/layouts/main-sidebar.blade.php` - Same navigation links for legacy sidebar

**Routes Updated (3 files):**
- `routes/admin.php` - Admin routes for Peak Hours, Profit Margins, Scheduled Reports
- `routes/api/pos.php` - API v1 endpoints for analytics and reports
- `routes/console.php` - Scheduled `report:generate` daily 8am / weekly Monday 8am

**Config Updated (1 file):**
- `config/mail.php` - Added `mailersend` mailer transport

**Dependencies:**
- Added `mailersend/laravel-driver` ^3.0

### Fixed
- Heatmap timezone: `CONVERT_TZ` corrects UTC-stored timestamps to app timezone for hour grouping

---

## [Unreleased] - 2026-01-27

### Added

#### Employee Schedules & Late Tracking System
Weekly schedule management for employees with automatic late detection.

**Model (1 file):**
- `app/Models/EmployeeSchedule.php` - Weekly schedule with day_of_week, start_time

**Migrations (2 files):**
- `database/migrations/2026_01_26_224245_create_employee_schedules_table.php` - Employee schedules table
- `database/migrations/2026_01_26_224415_add_late_columns_to_attendance_records_table.php` - Adds is_late, late_minutes to attendance_records

**Controllers (3 files):**
- `app/Http/Controllers/Admin/Employees/EmployeeScheduleController.php` - Admin web CRUD
- `app/Http/Controllers/API/v1/mobile/EmployeeScheduleController.php` - Mobile API
- `app/Http/Controllers/API/v1/mobile/AttendanceController.php` - Mobile attendance with late calculation

**Form Requests (1 file):**
- `app/Http/Requests/Admin/EmployeeSchedule/UpdateRequest.php` - Schedule validation

**Views (4 files):**
- `resources/views/admin/schedules/index.blade.php` - Employee schedules list
- `resources/views/admin/schedules/edit.blade.php` - Edit schedule form
- `resources/views/admin/schedules/_form.blade.php` - 7-day schedule form partial
- `resources/views/admin/schedules/table.blade.php` - AJAX table partial

**Config (1 file):**
- `config/attendance.php` - Grace period setting (default 15 minutes)

**Factory & Tests (2 files):**
- `database/factories/EmployeeScheduleFactory.php` - Schedule factory with states
- `tests/Feature/Admin/EmployeeScheduleTest.php` - Feature tests (8 tests)

**Routes:**
- Admin: `/admin/schedules`, `/admin/schedules/{user}/edit`, `/admin/schedules/table`
- API: `/api/v1/mobile/schedules`, `/api/v1/mobile/attendance/clock-in`, `/api/v1/mobile/attendance/clock-out`

### Changed

**Views Updated (3 files):**
- `resources/views/admin/attendance/index.blade.php` - Added "Late" filter option
- `resources/views/admin/attendance/table.blade.php` - Added Late column with badge
- `resources/views/admin/attendance/summary.blade.php` - Added Late Days and Late Minutes columns

**Controllers Updated (1 file):**
- `app/Http/Controllers/Admin/Employees/AttendanceController.php` - Handle late filter

**Sidebar Updated (1 file):**
- `resources/views/layout/layout/partials/sidebar/_menu.blade.php` - Added Schedules menu item

**Factories Updated (1 file):**
- `database/factories/AttendanceRecordFactory.php` - Added `late()` and `onTime()` states

**Models Updated (2 files):**
- `app/Models/User.php` - Added employeeSchedules relationship and calculateLate() method
- `app/Models/AttendanceRecord.php` - Added is_late and late_minutes attributes

**Resources Updated (1 file):**
- `app/Http/Resources/AttendanceRecordResource.php` - Added is_late and late_minutes

---

## [Unreleased] - 2026-01-04

### Added

#### Customer Authentication System (E-commerce)
Separate authentication system for customers, independent from admin users.

**Migration:**
- `database/migrations/2026_01_04_183649_add_auth_fields_to_customers_table.php` - Adds password, email_verified_at, remember_token

**Middleware (3 files):**
- `app/Http/Middleware/CustomerAuthenticate.php` - Web session authentication
- `app/Http/Middleware/CustomerApiAuthenticate.php` - API token authentication
- `app/Http/Middleware/RedirectIfCustomerAuthenticated.php` - Guest middleware

**Controllers (2 files):**
- `app/Http/Controllers/Customer/AuthController.php` - Web login/register/logout
- `app/Http/Controllers/API/v1/customer/AuthController.php` - API login/register/logout/me

**Form Requests (2 files):**
- `app/Http/Requests/Customer/LoginRequest.php` - Login validation
- `app/Http/Requests/Customer/RegisterRequest.php` - Registration validation

**Views (4 files):**
- `resources/views/customer/layouts/app.blade.php` - Customer layout
- `resources/views/customer/auth/login.blade.php` - Login form
- `resources/views/customer/auth/register.blade.php` - Registration form
- `resources/views/customer/dashboard.blade.php` - Customer dashboard

**Factory & Tests (3 files):**
- `database/factories/CustomerFactory.php` - Customer factory with states
- `tests/Feature/Customer/AuthenticationTest.php` - Web auth tests (11 tests)
- `tests/Feature/API/v1/customer/AuthControllerTest.php` - API auth tests (8 tests)

**Routes:**
- Web: `/customer/login`, `/customer/register`, `/customer/logout`, `/customer/dashboard`
- API: `/api/v1/customer/login`, `/api/v1/customer/register`, `/api/v1/customer/me`, `/api/v1/customer/logout`

**Guards Configured:**
- `customer` - Session-based web authentication
- `customer-api` - Passport token-based API authentication

---

## [Unreleased] - 2026-01-02

### Added

#### API Response Standardization
- **ApiResponse Trait** (`app/Http/Traits/ApiResponse.php`)
  - `success()` - Returns successful response with data (200)
  - `error()` - Returns error response with message (400)
  - `created()` - Returns 201 created response
  - `noContent()` - Returns 204 no content response
  - `notFound()` - Returns 404 not found response
  - `unauthorized()` - Returns 401 unauthorized response
  - `forbidden()` - Returns 403 forbidden response

#### API Resources (11 files)
- `app/Http/Resources/UserResource.php` - User data transformation
- `app/Http/Resources/RoleResource.php` - Role data transformation
- `app/Http/Resources/EmployeeResource.php` - Employee data transformation
- `app/Http/Resources/CategoryResource.php` - Category data transformation
- `app/Http/Resources/ItemResource.php` - Item/Product data transformation
- `app/Http/Resources/StoreResource.php` - Store data transformation
- `app/Http/Resources/SaleResource.php` - Sale data transformation
- `app/Http/Resources/SaleLineResource.php` - Sale line item transformation
- `app/Http/Resources/CustomerResource.php` - Customer data transformation
- `app/Http/Resources/PurchaseResource.php` - Purchase data transformation
- `app/Http/Resources/PurchaseLineResource.php` - Purchase line item transformation

#### Form Request Validation (2 files)
- `app/Http/Requests/API/v1/Auth/LoginRequest.php` - Login validation rules
- `app/Http/Requests/API/v1/pos/Sale/StoreRequest.php` - Sale creation validation rules

### Changed

#### API Controllers Updated (31 controllers)
All API controllers now use standardized response format with ApiResponse trait:

**Root Level (6):**
- `app/Http/Controllers/API/HomeController.php`
- `app/Http/Controllers/API/ItemController.php`
- `app/Http/Controllers/API/ReadingController.php`
- `app/Http/Controllers/API/ReceiptController.php`
- `app/Http/Controllers/API/SupplierController.php`
- `app/Http/Controllers/API/UserController.php`

**POS v1 (16):**
- `app/Http/Controllers/API/v1/pos/AuthenticationController.php`
- `app/Http/Controllers/API/v1/pos/CategoryController.php`
- `app/Http/Controllers/API/v1/pos/CustomerController.php`
- `app/Http/Controllers/API/v1/pos/ItemController.php`
- `app/Http/Controllers/API/v1/pos/OrderController.php`
- `app/Http/Controllers/API/v1/pos/PosLogController.php`
- `app/Http/Controllers/API/v1/pos/ReceiptController.php`
- `app/Http/Controllers/API/v1/pos/ReadingController.php`
- `app/Http/Controllers/API/v1/pos/RegisterController.php`
- `app/Http/Controllers/API/v1/pos/ReportController.php`
- `app/Http/Controllers/API/v1/pos/SaleController.php`
- `app/Http/Controllers/API/v1/pos/StoreController.php`
- `app/Http/Controllers/API/v1/pos/TaxController.php`
- `app/Http/Controllers/API/v1/pos/UnitController.php`
- `app/Http/Controllers/API/v1/pos/XreadingController.php`
- `app/Http/Controllers/API/v1/pos/ZreadingController.php`

**Mobile v1 (9):**
- `app/Http/Controllers/API/v1/mobile/CalendarController.php`
- `app/Http/Controllers/API/v1/mobile/CategoryController.php`
- `app/Http/Controllers/API/v1/mobile/ItemController.php`
- `app/Http/Controllers/API/v1/mobile/PurchaseController.php`
- `app/Http/Controllers/API/v1/mobile/ReportController.php`
- `app/Http/Controllers/API/v1/mobile/StoreController.php`
- `app/Http/Controllers/API/v1/mobile/SupplierController.php`
- `app/Http/Controllers/API/v1/mobile/UnitController.php`
- `app/Http/Controllers/API/v1/mobile/UserController.php`

#### Test Files Updated (6 files)
- `tests/Feature/API/v1/pos/CategoryControllerTest.php`
- `tests/Feature/API/v1/pos/ItemControllerTest.php`
- `tests/Feature/API/v1/pos/AuthenticationControllerTest.php`
- `tests/Feature/API/v1/mobile/ReportControllerTest.php`
- `tests/Feature/API/v1/pos/RegisterControllerTest.php`
- `tests/Feature/Admin/UserControllerTest.php`

### Fixed

#### File Rename
- `app/Http/Controllers/API/v1/pos/RceiptController.php` renamed to `ReceiptController.php` (typo fix)

#### Database Table References
Fixed incorrect `admin` table references to `users` in:
- `app/Http/Controllers/Auth/RegisterController.php` - `unique:admin` → `unique:users`
- `app/Http/Controllers/API/v1/pos/ReadingController.php` - `LEFT JOIN admin` → `LEFT JOIN users`
- `app/Http/Controllers/UserController.php` - `DB::table('admin')` → `DB::table('users')`
- `app/Http/Controllers/Admin/ProfileController.php` - `DB::table('admin')` → `DB::table('users')`

---

## Summary

| Category | Count |
|----------|-------|
| Files Created | 14 |
| Controllers Modified | 31 |
| Tests Updated | 6 |
| Bug Fixes | 5 |

### New Response Format
```json
{
    "success": true,
    "message": "Optional message",
    "data": { ... }
}
```
