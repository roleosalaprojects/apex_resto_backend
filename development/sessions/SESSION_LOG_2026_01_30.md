# Development Session Log - January 30, 2026

## Summary
This session implemented video advertisement support for the web admin, fixed several JavaScript and API issues, and updated the landing pages to link to the shop portal instead of admin login.

---

## 1. Video Advertisement Support

### Overview
Extended the advertisement system to support video uploads (MP4, WebM, MOV) in addition to images, with configurable duration limits.

### Migration
- `database/migrations/2026_01_30_001034_add_video_support_to_advertisements_table.php`
  - Added `media_type` (enum: image/video, default: image)
  - Added `duration` (integer, default: 10 seconds)
  - Added `status` (boolean, default: true)
  - Added `display_order` (integer, default: 0)

### Files Modified
- `app/Models/Advertisement.php`
  - Added new fields to `$fillable`
  - Added `isVideo()` and `isImage()` helper methods

- `app/Http/Requests/Advertisement/StoreRequest.php`
  - Dynamic validation based on media_type
  - Images: max 10MB, jpeg/png/jpg, duration 5-60 seconds
  - Videos: max 100MB, mp4/webm/mov, duration 5-300 seconds

- `app/Http/Requests/Advertisement/UpdateRequest.php`
  - Same dynamic validation as StoreRequest

- `app/Http/Controllers/Admin/HelperController.php`
  - Added `uploadMedia()` method for both images and videos

- `app/Http/Controllers/Admin/AdvertisementController.php`
  - Updated `store()` and `update()` to handle media_type, duration, status, display_order

- `resources/views/admin/advertisements/_fields.blade.php`
  - Complete redesign with card-based layout
  - Media type toggle (image/video)
  - Custom dropzone upload for both types with drag-drop support
  - Duration and display order settings

- `public/assets/js/pages/advertisements/create-edit.js`
  - Rewrote for custom dropzone handling
  - Toggle media inputs based on selected type
  - Manual validation with SweetAlert

- `resources/views/admin/advertisements/index.blade.php`
  - Added media type, duration, status columns

- `resources/views/admin/advertisements/show.blade.php`
  - Added video playback support

---

## 2. Video Streaming API

### Overview
Created a streaming endpoint with byte-range support for proper video playback in Flutter and web browsers.

### Files Created
- `app/Http/Controllers/API/v1/second_screen/MediaStreamController.php`
  - `stream()` method with HTTP 206 partial content support
  - Proper MIME type detection for mp4, webm, mov
  - 8KB chunk streaming

### Files Modified
- `routes/advertisements.php`
  - Added `/api/v1/media/stream/{filename}` route

- `app/Http/Controllers/API/v1/second_screen/AdvertisementController.php`
  - Returns streaming URL for videos, direct URL for images
  - Filters by active status only

---

## 3. Bug Fixes

### JavaScript TypeError Fix
- **Issue**: `TypeError: null is not an object (evaluating 'e.classList')` from plugins.bundle.js
- **Cause**: Metronic's FormValidation library and image-input component conflicting with custom upload
- **Solution**: Replaced Metronic image-input with custom dropzone, removed FormValidation, used manual validation

### Media Input Conflict Fix
- **Issue**: "Please upload an image or video file" error when uploading
- **Cause**: Both image and video inputs had `name="media"`, hidden one overwrote active one
- **Solution**: Toggle `disabled` state and `name` attribute based on active media type

### Flutter Video 404 Fix
- **Issue**: `HTTP 404: File Not Found` when playing videos in Flutter app
- **Cause**: MediaStreamController looking in wrong path (`img/advertisements/`)
- **Solution**: Changed to `public_path('media/advertisements/' . $filename)`

---

## 4. Description Nullable

### Migration
- `database/migrations/2026_01_30_002417_make_advertisement_description_nullable.php`
  - Changed description column to nullable

### Files Modified
- `app/Http/Controllers/Admin/AdvertisementController.php`
  - Uses `null` instead of empty string for missing description

---

## 5. Landing Page Updates

### Overview
Changed the RLCPS landing page to link to the shop portal instead of admin login, and updated the shop landing page text since delivery is not offered.

### Files Modified
- `resources/views/welcome.blade.php`
  - Changed "Sign In" button to "Shop Portal" linking to `/shop`
  - Updated both desktop and mobile nav

- `resources/views/ecommerce/index.blade.php`
  - Changed "Fresh Groceries, Delivered to You" to "Fresh Groceries, Ready for You"
  - Changed description to "Browse our wide selection of quality products and pick them up in-store."

---

## 6. Tests

### Files Created
- `database/factories/AdvertisementFactory.php`
- `tests/Feature/Admin/AdvertisementTest.php` (14 tests)
  - Index/create/store/show/edit/update/destroy pages
  - Store with image
  - Store with video
  - Update media type
  - Duration validation for images (max 60s)
  - Duration validation for videos (max 300s)
  - Status toggle
  - Display order

### Test Results
- All 14 advertisement tests pass

---

## 7. Dashboard Widgets

### Overview
Implemented real-time dashboard widgets for the admin dashboard: Sales Ticker, Top Products, Revenue Comparison, and Staff Leaderboard.

### Files Created

#### Livewire Components
- `app/Livewire/Admin/Dashboard/SalesTicker.php`
  - Real-time sales feed with 5-second polling
  - Shows latest 10 sales with cashier, store, and total
  - Excludes refunds (type=1)
  - Dispatches browser event when new sales arrive

- `app/Livewire/Admin/Dashboard/TopProducts.php`
  - Today's top 5 selling products by revenue
  - Shows product name, quantity sold, and total sales
  - Joins sale_lines with items table

- `app/Livewire/Admin/Dashboard/RevenueComparison.php`
  - Compares today's sales vs yesterday and last week same day
  - Calculates percentage change
  - Shows trend indicators (up/down arrows)

- `app/Livewire/Admin/Dashboard/StaffLeaderboard.php`
  - Top 5 cashiers ranked by total sales today
  - Shows transaction count, average transaction value
  - Progress bars showing contribution to team total

#### Blade Views
- `resources/views/livewire/admin/dashboard/sales-ticker.blade.php`
- `resources/views/livewire/admin/dashboard/top-products.blade.php`
- `resources/views/livewire/admin/dashboard/revenue-comparison.blade.php`
- `resources/views/livewire/admin/dashboard/staff-leaderboard.blade.php`

#### Factory
- `database/factories/PosFactory.php`
  - Factory for Pos model with all required fields
  - Includes `inactive()` state

#### Tests
- `tests/Feature/Admin/DashboardWidgetsTest.php` (14 tests)
  - Dashboard page loads with all widgets
  - Sales ticker shows recent sales
  - Sales ticker excludes refunds
  - Sales ticker checks for new sales
  - Top products shows bestsellers
  - Top products orders by total sales
  - Revenue comparison shows today/yesterday/last week
  - Revenue comparison calculates percentage change
  - Staff leaderboard shows top cashiers
  - Staff leaderboard orders by total sales
  - Staff leaderboard calculates total team sales
  - Widgets can be refreshed

### Files Modified
- `resources/views/admin/home.blade.php`
  - Added widgets row with Revenue Comparison, Top Products, Staff Leaderboard
  - Added Sales Ticker row at bottom

- `app/Models/Pos.php`
  - Added `HasFactory` trait for testing

- `resources/views/layout/layout/partials/header/_navbar.blade.php`
  - Fixed null-safe operator for user details image

- `resources/views/layout/partials/menus/_user-account-menu.blade.php`
  - Fixed null-safe operator for user details image

### Test Results
- All 14 dashboard widget tests pass

---

## 8. Recommendations Update

### Overview
Updated `recommendation.md` to mark implemented features and add new dashboard widget suggestions.

### Marked as Implemented (✅)
- Dashboard Widgets (Sales Ticker, Top Products, Revenue Comparison, Staff Leaderboard)
- Price History tracking
- Shift Management (clock in/out, cash reconciliation, breaks)
- Audit Trail
- Supplier Database
- Purchase Orders (with approval workflow, receiving, payments)
- Stock Transfers

### Marked as Partially Implemented
- Loyalty Program (points tracking done, tiered membership pending)
- Customer Self-Service Portal (auth/orders done, wishlist pending)

### New Widget Suggestions Added
1. Low Stock Alert Widget
2. Payment Methods Breakdown (pie chart)
3. Hourly Sales Heatmap
4. Customer Activity Widget
5. Inventory Value Widget
6. Pending Orders Widget
7. Expiring Soon Widget
8. Daily Sales Goal (progress bar)
9. Profit Margin Widget
10. Recent Activity Feed

---

## 9. Inventory Count App Spec Update

### Overview
Updated `development/apex_inventory_count_spec.md` with unit of measure (UoM) handling following the same pattern as apex_dashboard's Purchase Order module.

### Key Additions
- **Section 3: Unit of Measure Handling**
  - Product types (type=0 for PCS, type=1 for KGS)
  - Item Units with qty multipliers and unique barcodes per unit
  - Barcode scanning flow that detects unit from barcode
  - Unit selection dialog
  - Count entry with automatic conversion to base unit

- **Database Schema Updates**
  - Added unit_id, unit_name, unit_qty to count entries
  - Added counted_quantity (in selected unit) and counted_base_qty (converted)

- **API Updates**
  - Products endpoint returns item_units array
  - Barcode lookup returns matched_unit info
  - Count entry accepts unit fields

- **Flutter Models**
  - ProductModel with itemUnits list
  - ItemUnit and Unit models
  - CountEntry with unit handling
  - Barcode scanner controller logic

### Example Flow
```
Scan CASE barcode → Detect unit (CASE, qty=12)
Enter 4 CASES → Convert to 48 PCS
Compare vs system 45 PCS → Variance: +3 PCS
```
