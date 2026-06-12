# Cookie Second Screen Module — Advertisement System Spec

## Overview

The Cookie Second Screen Module is a customer-facing display system that shows advertisements (images and videos) on a secondary screen at the POS station. This module fetches advertisement content from the Apex backend API and cycles through them based on configured durations.

---

## Current System Analysis

### Existing Advertisement Table Structure

```
advertisements
├── id (bigint PK)
├── name (string)
├── description (string)
├── image (string) — file path
├── timestamps
└── soft_deletes
```

### Current Limitations
- Only supports image files (JPEG, PNG, JPG)
- No duration/timing control
- No media type distinction
- No status/active flag
- No display order/priority

---

## Proposed Schema Changes

### New Migration: Add Video Support & Duration

```sql
ALTER TABLE advertisements ADD COLUMN media_type ENUM('image', 'video') DEFAULT 'image' AFTER image;
ALTER TABLE advertisements ADD COLUMN duration INT UNSIGNED NOT NULL DEFAULT 5 AFTER media_type;
ALTER TABLE advertisements ADD COLUMN status BOOLEAN DEFAULT TRUE AFTER duration;
ALTER TABLE advertisements ADD COLUMN display_order INT UNSIGNED DEFAULT 0 AFTER status;
```

### Updated Table Structure

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | Primary key |
| `name` | string | Advertisement title |
| `description` | string | Brief description |
| `image` | string | File path (images: `img/advertisements/xxx.jpg`, videos: `img/advertisements/xxx.mp4`) |
| `media_type` | enum | `'image'` or `'video'` |
| `duration` | int (unsigned) | Display duration in seconds |
| `status` | boolean | Active/inactive flag |
| `display_order` | int (unsigned) | Sort order (lower = first) |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |
| `deleted_at` | timestamp | Soft delete |

---

## Duration Rules

### Image Advertisements
| Constraint | Value |
|------------|-------|
| Minimum | 5 seconds |
| Maximum | 60 seconds |
| Default | 10 seconds |

### Video Advertisements
| Constraint | Value |
|------------|-------|
| Minimum | 5 seconds |
| Maximum | 300 seconds (5 minutes) |
| Default | Video file duration (auto-detected) |

**Note:** For videos, if no duration is specified, the second screen client should play the full video. If a duration is set that's shorter than the video, it should stop at that point. If longer, it should loop.

---

## File Upload Specifications

### Supported Formats

**Images:**
- JPEG (.jpg, .jpeg)
- PNG (.png)
- Maximum file size: 10 MB

**Videos:**
- MP4 (.mp4)
- WebM (.webm)
- Maximum file size: 100 MB
- Recommended resolution: 1920x1080 (Full HD)
- Recommended codec: H.264 (MP4) or VP9 (WebM)

### Storage Location
```
public/img/advertisements/
├── image_abc123.jpg
├── image_def456.png
├── video_ghi789.mp4
└── video_jkl012.webm
```

---

## API Specification

### Endpoint: List Active Advertisements

```
GET /api/v1/advertisements
```

Returns all active advertisements sorted by `display_order`.

**Response (200):**

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Summer Sale",
      "description": "50% off on selected items",
      "image": "img/advertisements/summer_sale.jpg",
      "media_type": "image",
      "duration": 10,
      "media_url": "https://yourdomain.com/img/advertisements/summer_sale.jpg"
    },
    {
      "id": 2,
      "name": "Store Promo Video",
      "description": "Welcome to our store",
      "image": "img/advertisements/promo.mp4",
      "media_type": "video",
      "duration": 120,
      "media_url": "https://yourdomain.com/img/advertisements/promo.mp4"
    }
  ]
}
```

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `type` | string | Filter by media type: `image`, `video`, or `all` (default) |

---

## Admin Panel Changes

### Create/Edit Advertisement Form

**Fields:**

1. **Name** (required, string, max 255)
2. **Description** (required, string, max 255)
3. **Media Type** (required, radio/select)
   - Image
   - Video
4. **Media File** (required for create, optional for update)
   - Dynamic accept attribute based on media type
   - Image: `.jpg,.jpeg,.png`
   - Video: `.mp4,.webm`
5. **Duration** (required, number input)
   - Min/max validation based on media type
   - Show helper text: "Image: 5-60 seconds, Video: 5-300 seconds"
6. **Status** (checkbox/toggle)
   - Active / Inactive
7. **Display Order** (number input)
   - Lower numbers display first

### DataTable Columns

| Column | Description |
|--------|-------------|
| Preview | Thumbnail for images, video icon for videos |
| Name | Advertisement name |
| Type | Badge: "Image" or "Video" |
| Duration | X seconds |
| Status | Active/Inactive badge |
| Order | Display order number |
| Actions | View, Edit, Delete |

---

## Validation Rules

### StoreRequest

```php
public function rules(): array
{
    $rules = [
        'name' => ['required', 'string', 'max:255'],
        'description' => ['required', 'string', 'max:255'],
        'media_type' => ['required', 'in:image,video'],
        'status' => ['nullable', 'boolean'],
        'display_order' => ['nullable', 'integer', 'min:0'],
    ];

    if ($this->media_type === 'video') {
        $rules['image'] = ['required', 'file', 'mimes:mp4,webm', 'max:102400']; // 100MB
        $rules['duration'] = ['required', 'integer', 'min:5', 'max:300'];
    } else {
        $rules['image'] = ['required', 'file', 'image', 'mimes:jpeg,png,jpg', 'max:10240']; // 10MB
        $rules['duration'] = ['required', 'integer', 'min:5', 'max:60'];
    }

    return $rules;
}
```

### UpdateRequest

```php
public function rules(): array
{
    $rules = [
        'name' => ['required', 'string', 'max:255'],
        'description' => ['required', 'string', 'max:255'],
        'media_type' => ['required', 'in:image,video'],
        'old_image' => ['required', 'string'],
        'status' => ['nullable', 'boolean'],
        'display_order' => ['nullable', 'integer', 'min:0'],
    ];

    if ($this->media_type === 'video') {
        $rules['image'] = ['nullable', 'file', 'mimes:mp4,webm', 'max:102400'];
        $rules['duration'] = ['required', 'integer', 'min:5', 'max:300'];
    } else {
        $rules['image'] = ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg', 'max:10240'];
        $rules['duration'] = ['required', 'integer', 'min:5', 'max:60'];
    }

    return $rules;
}
```

---

## Second Screen Client Behavior

### Advertisement Cycle Flow

```
┌─────────────────────────────────────────────────────────────┐
│                    Second Screen Client                      │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
                    ┌─────────────────┐
                    │  Fetch Ads API  │
                    │  GET /api/v1/   │
                    │  advertisements │
                    └────────┬────────┘
                              │
                              ▼
                    ┌─────────────────┐
                    │ Sort by         │
                    │ display_order   │
                    └────────┬────────┘
                              │
              ┌───────────────┴───────────────┐
              │                               │
              ▼                               ▼
    ┌─────────────────┐             ┌─────────────────┐
    │  media_type =   │             │  media_type =   │
    │     'image'     │             │     'video'     │
    └────────┬────────┘             └────────┬────────┘
              │                               │
              ▼                               ▼
    ┌─────────────────┐             ┌─────────────────┐
    │ Display image   │             │ Play video      │
    │ for `duration`  │             │ for `duration`  │
    │ seconds         │             │ seconds or full │
    └────────┬────────┘             └────────┬────────┘
              │                               │
              └───────────────┬───────────────┘
                              │
                              ▼
                    ┌─────────────────┐
                    │  Next Ad in     │
                    │  cycle (loop)   │
                    └─────────────────┘
```

### Client Requirements

1. **Polling Interval:** Refresh advertisement list every 5 minutes
2. **Preloading:** Preload next advertisement while current one displays
3. **Fallback:** Show company logo if no advertisements available
4. **Error Handling:** Continue cycling if one ad fails to load
5. **Fullscreen:** Run in fullscreen/kiosk mode
6. **Video Behavior:**
   - Auto-play with sound muted by default
   - Loop if duration exceeds video length
   - Stop at duration if duration < video length

---

## Implementation Checklist

### Backend (Apex)

- [ ] Create migration to add `media_type`, `duration`, `status`, `display_order` columns
- [ ] Update `Advertisement` model with new fillable fields and casts
- [ ] Update `StoreRequest` validation with dynamic rules
- [ ] Update `UpdateRequest` validation with dynamic rules
- [ ] Update `AdvertisementController@store` to handle video uploads
- [ ] Update `AdvertisementController@update` to handle video uploads
- [ ] Update API controller to return `media_url` and filter by status
- [ ] Update admin create/edit views with new fields
- [ ] Update DataTable to show new columns
- [ ] Write tests for video upload and duration validation

### Frontend (Cookie Second Screen)

- [ ] Update API response handling for new fields
- [ ] Implement video player component
- [ ] Implement image display with timer
- [ ] Implement advertisement cycling logic
- [ ] Add preloading for smooth transitions
- [ ] Handle fullscreen/kiosk mode
- [ ] Add error handling and fallback display

---

## Configuration (Optional)

Add to `config/advertisements.php`:

```php
<?php

return [
    'image' => [
        'min_duration' => 5,    // seconds
        'max_duration' => 60,   // seconds
        'default_duration' => 10,
        'max_file_size' => 10240, // KB (10 MB)
        'allowed_mimes' => ['jpeg', 'png', 'jpg'],
    ],
    'video' => [
        'min_duration' => 5,     // seconds
        'max_duration' => 300,   // seconds (5 minutes)
        'default_duration' => null, // null = use video length
        'max_file_size' => 102400, // KB (100 MB)
        'allowed_mimes' => ['mp4', 'webm'],
    ],
    'polling_interval' => 300, // seconds (5 minutes)
];
```

---

## Related Files

| File | Purpose |
|------|---------|
| `app/Models/Advertisement.php` | Advertisement model |
| `app/Http/Controllers/Admin/Settings/AdvertisementController.php` | Admin CRUD |
| `app/Http/Controllers/API/v1/second_screen/AdvertisementController.php` | API for second screen |
| `app/Http/Requests/Advertisement/StoreRequest.php` | Create validation |
| `app/Http/Requests/Advertisement/UpdateRequest.php` | Update validation |
| `resources/views/admin/advertisements/*.blade.php` | Admin views |
| `routes/advertisements.php` | API routes |
| `database/migrations/*_create_advertisements_table.php` | Original migration |

---

## Security Considerations

1. **File Validation:** Strictly validate MIME types to prevent malicious uploads
2. **File Size Limits:** Enforce max file sizes to prevent storage abuse
3. **Storage Location:** Store in `public/img/` for direct access (no auth required for display)
4. **Sanitization:** Sanitize file names to prevent path traversal attacks
5. **Rate Limiting:** Consider rate limiting the API endpoint if exposed publicly
