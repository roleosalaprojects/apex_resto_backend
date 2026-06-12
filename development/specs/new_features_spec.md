# New Features - Mobile API Specification

## Overview

This document details the new features added to the Apex Backend system and their corresponding Mobile API endpoints for the Apex Dashboard app.

---

## 1. Shop Announcements System

### Purpose
A promotional content system for displaying announcements, banners, and notifications to users across both web and mobile platforms.

### Database Schema

**Table:** `shop_announcements`

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| title | varchar(255) | Announcement title |
| description | text | Optional description/subtitle |
| media_path | varchar(500) | Path to uploaded image/video |
| media_type | enum('image','video') | Type of media content |
| link_url | varchar(500) | Optional deep link or URL |
| link_text | varchar(100) | Optional button/CTA text |
| position | enum('hero','banner','popup') | Display position in app |
| display_order | int | Sort order (lower = first) |
| is_active | boolean | Active/inactive toggle |
| starts_at | timestamp | Schedule start (nullable) |
| ends_at | timestamp | Schedule end (nullable) |
| created_at | timestamp | Record creation |
| updated_at | timestamp | Last update |

### Model Scopes

```php
ShopAnnouncement::active()      // is_active = true
ShopAnnouncement::scheduled()   // Within starts_at/ends_at range
ShopAnnouncement::hero()        // position = 'hero'
ShopAnnouncement::banner()      // position = 'banner'
ShopAnnouncement::popup()       // position = 'popup'
ShopAnnouncement::ordered()     // Order by display_order ASC
```

### Mobile API Endpoints

#### Get All Active Announcements
```
GET /api/v1/mobile/announcements
```

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| position | string | Filter by position: hero, banner, popup |
| limit | int | Maximum results (default: 10) |

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "title": "Summer Sale - 20% Off",
      "description": "Get 20% off on all products this summer!",
      "media_url": "https://api.apex-pos.com/shop_announcements/summer-sale.jpg",
      "media_type": "image",
      "link_url": "/products?sale=summer",
      "link_text": "Shop Now",
      "position": "hero",
      "display_order": 1
    }
  ]
}
```

#### Get Single Announcement
```
GET /api/v1/mobile/announcements/{id}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "title": "Summer Sale - 20% Off",
    "description": "Get 20% off on all products this summer!",
    "media_url": "https://api.apex-pos.com/shop_announcements/summer-sale.jpg",
    "media_type": "image",
    "link_url": "/products?sale=summer",
    "link_text": "Shop Now",
    "position": "hero",
    "display_order": 1,
    "starts_at": "2026-06-01T00:00:00Z",
    "ends_at": "2026-08-31T23:59:59Z"
  }
}
```

### Mobile App Usage

**Dashboard Home Screen:**
- Fetch hero announcements for carousel/banner display
- Auto-rotate announcements every 5 seconds
- Support both image and video media

**Popup Notifications:**
- Fetch popup announcements on app launch
- Show once per session or based on scheduling
- Include dismiss action

**Banner Ads:**
- Display banner announcements in product lists
- Insert between product rows

---

## 2. Category Icons

### Purpose
Allow administrators to assign custom icons (emoji or text) to product categories for enhanced visual display in the mobile app.

### Database Changes

Added column to `categories` table:
- `icon` - varchar(100), nullable

### Mobile API Endpoints

#### Get Categories with Icons
```
GET /api/v1/mobile/categories
```

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| with_count | boolean | Include item count (default: true) |
| active_only | boolean | Only active categories (default: true) |

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Fresh Meat",
      "icon": "🥩",
      "description": "Premium quality fresh meat",
      "image": "categories/fresh-meat.jpg",
      "items_count": 45
    },
    {
      "id": 2,
      "name": "Vegetables",
      "icon": "🥬",
      "description": "Farm fresh vegetables",
      "image": null,
      "items_count": 32
    },
    {
      "id": 3,
      "name": "Beverages",
      "icon": "🥤",
      "description": null,
      "image": "categories/beverages.jpg",
      "items_count": 28
    }
  ]
}
```

#### Get Single Category
```
GET /api/v1/mobile/categories/{id}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Fresh Meat",
    "icon": "🥩",
    "description": "Premium quality fresh meat",
    "image": "https://api.apex-pos.com/categories/fresh-meat.jpg",
    "items_count": 45,
    "items": [
      {
        "id": 101,
        "name": "Beef Ribeye",
        "price": 450.00,
        "image": "items/beef-ribeye.jpg"
      }
    ]
  }
}
```

### Mobile App Usage

**Category Grid:**
- Display icon prominently (fallback to default if null)
- Show category name and item count
- Support both emoji icons and image fallback

**Navigation:**
- Use icons in bottom nav or side menu
- Category filter chips with icons

---

## 3. Implementation Requirements

### API Resource Classes

Create Eloquent API Resources for consistent responses:

**ShopAnnouncementResource.php:**
```php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ShopAnnouncementResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'media_url' => $this->media_url,
            'media_type' => $this->media_type,
            'link_url' => $this->link_url,
            'link_text' => $this->link_text,
            'position' => $this->position,
            'display_order' => $this->display_order,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
        ];
    }
}
```

**CategoryResource.php (update existing):**
```php
// Add icon field to existing resource
'icon' => $this->icon,
'description' => $this->description,
'image' => $this->image ? url($this->image) : null,
```

### Controller Methods

**MobileAnnouncementController.php:**
```php
namespace App\Http\Controllers\Api\V1\Mobile;

class MobileAnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $query = ShopAnnouncement::active()->scheduled()->ordered();

        if ($request->has('position')) {
            $query->where('position', $request->position);
        }

        $limit = $request->get('limit', 10);
        $announcements = $query->limit($limit)->get();

        return ShopAnnouncementResource::collection($announcements);
    }

    public function show(ShopAnnouncement $announcement)
    {
        if (!$announcement->isCurrentlyActive()) {
            return response()->json(['message' => 'Announcement not found'], 404);
        }

        return new ShopAnnouncementResource($announcement);
    }
}
```

### Routes

Add to `routes/api.php`:
```php
Route::prefix('v1/mobile')->middleware('auth:api')->group(function () {
    // Announcements
    Route::get('announcements', [MobileAnnouncementController::class, 'index']);
    Route::get('announcements/{announcement}', [MobileAnnouncementController::class, 'show']);

    // Categories (update existing to include icon)
    Route::get('categories', [MobileCategoryController::class, 'index']);
    Route::get('categories/{category}', [MobileCategoryController::class, 'show']);
});
```

---

## 4. Admin Management

### Shop Announcements Admin

**Route Prefix:** `/admin/shop-announcements`

| Method | URI | Action |
|--------|-----|--------|
| GET | / | List all announcements |
| GET | /create | Show create form |
| POST | / | Save new announcement |
| GET | /{id}/edit | Show edit form |
| PUT | /{id} | Update announcement |
| DELETE | /{id} | Delete announcement |

**Features:**
- Media upload (image/video)
- Scheduling with start/end dates
- Position selection (hero/banner/popup)
- Display order management
- Active/inactive toggle

### Category Icons Admin

**Location:** Existing category management at `/admin/categories`

**Added Fields:**
- Icon input (emoji or text, max 100 chars)
- Description textarea
- Image upload

---

## 5. Testing Checklist

### Mobile API Tests
- [ ] GET /announcements returns active, scheduled announcements
- [ ] GET /announcements?position=hero filters correctly
- [ ] GET /announcements/{id} returns 404 for inactive
- [ ] GET /categories includes icon field
- [ ] GET /categories/{id} includes items with proper structure
- [ ] Media URLs are absolute and accessible
- [ ] Responses match documented schema

### Admin Tests
- [ ] Create announcement with image
- [ ] Create announcement with video
- [ ] Update announcement scheduling
- [ ] Delete announcement
- [ ] Add icon to category
- [ ] Update category icon
- [ ] Icon displays in category list

---

## 6. Files Reference

### New Files
- `app/Models/ShopAnnouncement.php`
- `app/Http/Controllers/Admin/Ecommerce/ShopAnnouncementController.php`
- `app/Http/Controllers/Api/V1/Mobile/MobileAnnouncementController.php` (to create)
- `app/Http/Resources/ShopAnnouncementResource.php` (to create)
- `database/migrations/2026_02_03_005157_create_shop_announcements_table.php`
- `database/migrations/2026_02_03_010740_add_icon_to_categories_table.php`

### Modified Files
- `app/Models/Category.php` - Added icon, description to fillable
- `app/Http/Resources/CategoryResource.php` - Add icon field
- `routes/api.php` - Add announcement routes

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | 2026-02-03 | Initial implementation |
