# Tenant Branding — Plan (Backend + Web)

**Status:** Planning. Not yet approved to build.
**Date:** 2026-06-05
**Owner:** Richard
**Scope (this doc):** apex_backend — storage, SuperAdmin palette CRUD, tenant admin Branding page, /shop theming, admin theming, email theming, API endpoint for apex_pos.
**Companion spec:** `apex_pos_spec.md` (Flutter integration, executed by a separate agent against `../apex_pos`). apex_pos always follows what the backend defines.
**Out of scope:** Receipts (existing Receipt Settings); printed labels.

---

## Confirmed decisions

- **Color UX:** curated palettes only (no freeform hex pickers exposed to tenants).
- **Palette source:** DB-backed, CRUD'd by **SuperAdmin** at `/superadmin/color-palettes`. Tenants only choose from active palettes.
- **Security:** all `must-have` AND both `should-have` items in §1 are in scope for v1. No deferrals.
- **SuperAdmin sidebar placement:** new top-level "**Appearance**" group, with "Color Palettes" as its first item. Future appearance-related links (e.g., per-store theming) land in the same group.
- **`brand_name` overrides HTML `<title>`:** when set, page titles render as `"{brand_name} — {page}"` instead of `"Apex — {page}"`. Implemented via a Blade `@section('title')` resolver that prepends the brand.
- **POS scope:** backend + /shop + admin + emails + API endpoint in this PR. Flutter changes spec'd separately for another agent.
- **Surfaces theming:** /shop, admin backoffice, email templates. NOT receipts. NOT labels.
- **Working branch:** `feature/tenant-branding`, off `dev` (created 2026-06-05).

---

## 1. Security analysis (must-resolve BEFORE build)

Injecting admin-controlled values into `<style>` blocks or `<img src>` is a real attack surface. This section enumerates each risk and the validated mitigation. **All v1 must-haves are blockers — the build does not start until they are designed in.**

### 1.1 CSS injection via color values

**Attack.** An adversarial palette author submits a color value like:
```
red; } body { display: none; } :root { --bs-primary: red
```
Rendered into `<style> :root { --bs-primary: COLOR; } </style>`, the closing brace breaks out of the rule, injecting arbitrary CSS. Consequences:
- Hide UI elements (a Logout link, Confirm button).
- Overlay phishing content with `position: fixed; z-index: 9999`.
- Exfiltrate input values via CSS attribute selectors + `background-image: url(...)` callbacks.
- `@import url(//evil)` pulls in attacker-controlled stylesheet.

`expression()` and `javascript:` URLs are blocked by modern browsers, but the CSS-only attacks above remain valid.

**Severity:** HIGH. **Cost to mitigate:** LOW.

**Mitigation (v1 must-have):**
- Strict server-side validation on every color field, including SuperAdmin input:
  `'regex:/^#[0-9A-Fa-f]{6}$/'`
- Store normalised lowercase hex: `strtolower($validated['primary'])`.
- Defense-in-depth: a `BrandingService::sanitizeHex(string $hex): string` guard at the read path that re-validates before emitting. Throws on mismatch, falls back to default palette value. This protects against future code that bypasses validation OR a tampered DB row.

### 1.2 Logo upload — SVG XSS

**Attack.** An uploaded SVG contains `<script>`, `<svg onload="…">`, `<foreignObject>` with embedded HTML, or external entity refs. Served from the same origin, it executes in the user's browser → stored XSS.

**Severity:** HIGH. **Cost to mitigate:** LOW (by disallowing SVG).

**Mitigation (v1 must-have):**
- **Disallow SVG entirely in v1.** Accept only `png`, `jpg`, `jpeg`, `webp`.
- Validate via FormRequest: `'mimes:png,jpg,jpeg,webp'` AND `'mimetypes:image/png,image/jpeg,image/webp'` (the former checks extension, the latter checks magic bytes).
- After FormRequest passes, additionally verify via `getimagesize()` that the file is decodable as one of those formats. Reject anything where `getimagesize` returns false or reports a different MIME.
- If SVG support is ever needed: require adding `enshrined/svg-sanitize` and routing every SVG through it. Treat as future work.

### 1.3 Logo upload — polyglot files

**Attack.** A "polyglot" file is valid PNG **and** valid JS/HTML. Browsers may execute it if Content-Type is wrong or MIME sniffing is on.

**Severity:** LOW–MEDIUM. **Cost to mitigate:** MEDIUM.

**Mitigation (v1 should-have):**
- Re-encode every uploaded image via GD (Laravel built-in, no extra package needed):
  ```php
  $img = imagecreatefromstring(file_get_contents($upload->path()));
  imagepng($img, $destPath); // or jpeg/webp based on requested output
  imagedestroy($img);
  ```
  Re-encoding strips all non-image bytes, destroying any polyglot payload. **Recommended.**
- Add a single global middleware that sets `X-Content-Type-Options: nosniff` on all responses. One line; reasonable hardening. **Recommended.**
- Laravel's `Storage::url()` already sets correct Content-Type from extension.

If GD re-encoding adds friction, the v1 minimum is `nosniff` header + extension/MIME match. Both layers together are strictly better.

### 1.4 Stored XSS via `brand_name`

**Attack.** `brand_name = "<script>alert(1)</script>"`.

**Severity:** LOW (Blade escapes by default). **Cost to mitigate:** LOW.

**Mitigation (v1 must-have):**
- Validate `brand_name`: max 60 chars, restricted charset:
  `'regex:/^[\\p{L}\\p{N}\\s&\\-.\']+$/u'` (letters, numbers, spaces, `& - . '`).
- All Blade output uses `{{ }}` (never `{!! !!}`). No exceptions.
- When embedded in JSON for JS: use Blade's `@json()` directive, which calls `json_encode($, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)`.
- Never render into a `<style>` block or `<script>` block.

### 1.5 Tenant boundary

**Attack.** Tenant A sends `PUT /admin/settings/branding` with `user_id=B` to overwrite tenant B's branding (or `GET` to read it).

**Severity:** HIGH. **Cost to mitigate:** LOW.

**Mitigation (v1 must-have):**
- Controllers never read `user_id` from request input. Always derive from `auth()->user()->user_id`.
- `BrandingService::forCurrentTenant()` reads from auth context only.
- API endpoint resolves tenant from the OAuth token (Passport / `auth:api`), not from a query parameter.
- SuperAdmin palette CRUD is gated by `auth:superadmin` (existing pattern). Tenant admins cannot reach those routes.
- Add a feature test that asserts tenant A cannot mutate tenant B's branding row.

### 1.6 Path traversal during upload

**Attack.** Manipulated filename like `../../../../etc/passwd`.

**Severity:** MEDIUM. **Cost to mitigate:** LOW.

**Mitigation (v1 must-have):**
- `logo_path` is never accepted as user input — only the file itself.
- Server-generates the storage path: `branding/{user_id}/{random40}.{ext}` using `Storage::disk('public')->putFileAs(...)`.
- `ext` is determined server-side from `getimagesize()` result, not from the upload's filename.

### 1.7 Information disclosure via logo URL

**Note.** `Storage::url('branding/12/abc.png')` yields a predictable public URL. Logos are meant to be public (they render on /shop) so this is acceptable.

**v1 stance:** No mitigation needed. If a tenant later wants private logos (unlikely), switch to signed URLs.

### 1.8 Cache poisoning

**Note.** Branding is cached per-tenant in Redis/file under key `branding.{user_id}`. Same security posture as the rest of the app's cache — relies on infrastructure access controls. No application-level mitigation needed beyond scoping the cache key.

### 1.9 Summary table

| Risk | Severity | v1 mitigation | Status |
|---|---|---|---|
| CSS injection from non-hex color | HIGH | Strict hex regex + read-path guard | **must-have** |
| SVG XSS | HIGH | Disallow SVG; PNG/JPG/WEBP only | **must-have** |
| Polyglot image | LOW–MED | GD re-encode + `nosniff` header | **should-have** |
| `brand_name` XSS | LOW | Charset regex + Blade `{{ }}` only | **must-have** |
| Tenant boundary | HIGH | Auth-derived tenant; never trust input `user_id` | **must-have** |
| Path traversal | MED | Server-generated storage path | **must-have** |
| Logo URL disclosure | INFO | n/a — logos are public | accepted |
| Cache poisoning | LOW | Scoped key; infra-level | accepted |
| MIME sniffing | LOW | `X-Content-Type-Options: nosniff` middleware | **should-have** |

**Decision required before build:** confirm we adopt all `must-have` items and the two `should-have` items. If GD re-encode is rejected, document the trade-off explicitly.

---

## 2. Data model

### 2.1 `color_palettes` (new — SuperAdmin-managed)

| column | type | notes |
|---|---|---|
| `id` | bigInt PK | |
| `key` | string(64), unique | Slug, e.g. `apex_default`, `ocean_breeze`. Referenced by `branding_settings.palette_key`. |
| `label` | string(80) | Human-readable name. |
| `primary` | string(7) | `#RRGGBB`, lowercase. Validated hex. |
| `secondary` | string(7) | hex |
| `accent` | string(7) | hex |
| `on_primary` | string(7) | Text colour on primary surface. hex |
| `on_secondary` | string(7) | hex |
| `is_default` | boolean | Exactly one row has `is_default=true` (enforced by observer). Used when tenant has no setting or assigned palette becomes inactive. |
| `is_active` | boolean | Inactive palettes are hidden from tenant picker but still resolvable for legacy assignments. |
| `sort_order` | integer | Display order in tenant picker. |
| `created_at`, `updated_at` | timestamps | |
| `deleted_at` | softDeletes | Allows historical reference. Cannot delete `is_default`. |

Migration: `database/migrations/{ts}_create_color_palettes_table.php`.
Model: `App\Models\Settings\ColorPalette`.
Seeder: `database/seeders/ColorPaletteSeeder.php` — inserts ~10 starter palettes including `apex_default` (is_default=true).
Observer: `App\Observers\ColorPaletteObserver` — enforces single-default invariant, prevents deleting default, invalidates branding cache when a palette changes.

### 2.2 `branding_settings` (new — tenant-scoped)

| column | type | notes |
|---|---|---|
| `id` | bigInt PK | |
| `user_id` | bigInt, unique | FK → `users.id`. One row per tenant owner. |
| `palette_key` | string(64) | FK-style reference to `color_palettes.key`. Not a true DB FK so palette rename doesn't cascade. |
| `logo_path` | string nullable | Relative path inside `public` disk: `branding/{user_id}/{random}.{ext}`. |
| `brand_name` | string(60) nullable | Replaces "APEX" wordmark when set. |
| `created_at`, `updated_at` | timestamps | `updated_at` used as cache-buster and Flutter sync key. |

Model: `App\Models\Settings\BrandingSetting`. Belongs to `User`.
Observer: `App\Observers\BrandingSettingObserver` — invalidates `Cache::forget("branding.{$userId}")` on save/delete.

---

## 3. Branding service

```php
namespace App\Services;

class BrandingService
{
    public function __construct(private CacheManager $cache) {}

    public function forCurrentTenant(): array
    {
        $userId = auth()->user()?->user_id ?? null;
        return $userId
            ? $this->forTenant($userId)
            : $this->defaultPayload();
    }

    public function forTenant(int $tenantUserId): array
    {
        return $this->cache->remember(
            "branding.{$tenantUserId}",
            now()->addMinutes(5),
            fn () => $this->resolve($tenantUserId)
        );
    }

    private function resolve(int $userId): array
    {
        $setting = BrandingSetting::where('user_id', $userId)->first();
        $palette = $this->palette($setting?->palette_key);
        return [
            'palette_key' => $palette->key,
            'primary' => $this->sanitizeHex($palette->primary),
            'secondary' => $this->sanitizeHex($palette->secondary),
            'accent' => $this->sanitizeHex($palette->accent),
            'on_primary' => $this->sanitizeHex($palette->on_primary),
            'on_secondary' => $this->sanitizeHex($palette->on_secondary),
            'logo_url' => $setting?->logo_path && Storage::disk('public')->exists($setting->logo_path)
                ? Storage::url($setting->logo_path)
                : null,
            'brand_name' => $setting?->brand_name ?: 'APEX',
            'updated_at' => $setting?->updated_at?->toIso8601String(),
        ];
    }

    private function palette(?string $key): ColorPalette
    {
        $palette = $key
            ? ColorPalette::where('key', $key)->first()
            : null;
        if (! $palette || ! $palette->is_active) {
            $palette = ColorPalette::where('is_default', true)->firstOrFail();
        }
        return $palette;
    }

    private function sanitizeHex(string $hex): string
    {
        return preg_match('/^#[0-9a-f]{6}$/', $hex) === 1
            ? $hex
            : '#1858fd'; // hardcoded Apex blue fallback if a DB row was tampered with
    }

    private function defaultPayload(): array { /* derived from default palette */ }
}
```

Used by:
- Web layouts (composed via a Blade `@inject` or a View Composer that exposes `$branding` to all admin + shop + mail views).
- API endpoint (resolves the authenticated POS user's tenant).

---

## 4. SuperAdmin — palette CRUD

Routes added under existing `Route::prefix('/superadmin')->middleware('auth:superadmin')` group in `routes/superadmin.php`:

```php
Route::prefix('/color-palettes')->name('superadmin.color-palettes.')
    ->controller(\App\Http\Controllers\SuperAdmin\ColorPaletteController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/data', 'data')->name('data');                  // DataTables
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{palette}/edit', 'edit')->name('edit');
        Route::put('/{palette}', 'update')->name('update');
        Route::delete('/{palette}', 'destroy')->name('destroy');
        Route::post('/{palette}/set-default', 'setDefault')->name('set-default');
        Route::post('/{palette}/toggle-active', 'toggleActive')->name('toggle-active');
    });
```

Controller: `App\Http\Controllers\SuperAdmin\ColorPaletteController`.

Form Requests:
- `StoreColorPaletteRequest`
- `UpdateColorPaletteRequest`

Validation (both):
```php
'key' => ['required', 'string', 'alpha_dash', 'max:64', Rule::unique('color_palettes')->ignore($this->palette?->id)],
'label' => ['required', 'string', 'max:80'],
'primary' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
'secondary' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
'accent' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
'on_primary' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
'on_secondary' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
'is_active' => ['required', 'boolean'],
'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
```

Views:
- `resources/views/superadmin/color-palettes/index.blade.php` — DataTable list (key, label, swatch preview, default? active? actions).
- `resources/views/superadmin/color-palettes/create.blade.php` and `edit.blade.php` — shared `_form.blade.php` partial. Five colour pickers (HTML5 `type=color`, value mirrored to hex text input), live preview pane.

Behaviour rules:
- Cannot delete `is_default=true` palette (controller throws 422 with explanatory message).
- `setDefault` action: marks the chosen palette default and clears `is_default` on the previous default in a single transaction.
- Tenants currently assigned a palette that is later soft-deleted or `is_active=false` fall back to the default automatically via `BrandingService::palette()`.
- **Sidebar placement:** new top-level "**Appearance**" group in the SuperAdmin sidebar (`resources/views/superadmin/_layout.blade.php` or equivalent). "Color Palettes" is the first item under Appearance. Active route highlighting follows the existing `request()->routeIs('superadmin.color-palettes.*') ? 'here show' : ''` pattern used elsewhere.

Tests (`tests/Feature/SuperAdmin/ColorPaletteControllerTest.php`):
- SuperAdmin can list, create, update, set-default, soft-delete a palette.
- Non-superadmin (regular user, admin role) is redirected / 403.
- Validation rejects non-hex `#xyz` colors.
- Validation rejects duplicate `key`.
- Cannot delete default palette.
- `set-default` clears the previous default.
- Tenant with an assigned-but-soft-deleted palette resolves to default at runtime (covered in `BrandingServiceTest`).

---

## 5. Tenant admin — Branding page

Route: `/admin/settings/branding` (GET, PUT) → `App\Http\Controllers\Admin\Settings\BrandingController`.

View: `resources/views/admin/settings/branding/index.blade.php`.

Sections:
- **Card 1 — Brand identity:**
  - Brand name text input (max 60, charset-restricted, falls back to "APEX").
  - Logo upload — PNG/JPG/JPEG/WEBP, ≤500KB, max 1200×400 px. Existing logo shown with "Remove" action.
  - Live preview of navbar mock with current logo + brand name.
- **Card 2 — Palette:**
  - Grid of active palette swatches (each shows label + 5 colour chips).
  - Selected one highlighted; saves `palette_key`.
  - Live mini-preview pane (mock navbar + button + accent text).

Form Request: `App\Http\Requests\Admin\Settings\UpdateBrandingRequest`:
```php
'palette_key' => ['required', 'string', Rule::exists('color_palettes', 'key')->where('is_active', true)],
'brand_name' => ['nullable', 'string', 'max:60', 'regex:/^[\\p{L}\\p{N}\\s&\\-.\']+$/u'],
'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'mimetypes:image/png,image/jpeg,image/webp', 'max:500', 'dimensions:max_width=1200,max_height=400'],
```

Controller logic:
- Reads `auth()->user()->user_id`, never input.
- If `logo` present: re-encode via GD; store via `Storage::disk('public')->putFileAs("branding/{$userId}", $file, Str::random(40) . '.' . $ext)`. Delete prior logo file if any.
- Updates or creates `BrandingSetting` row for tenant.
- Observer invalidates cache.

Sidebar item: add "Branding" under existing Settings group in `resources/views/layout/layout/partials/sidebar/_menu.blade.php`.

---

## 6. Web rendering

### 6.1 Branding context exposed everywhere

Register a View Composer in `AppServiceProvider::boot()`:

```php
View::composer(['layout.app', 'ecommerce.*', 'vendor.mail.*'], function ($view) {
    $view->with('branding', app(BrandingService::class)->forCurrentTenant());
});
```

### 6.1.1 HTML `<title>` override

`brand_name` overrides the leading word in every page title. Two layers:

- **Admin/Superadmin (Metronic layout):** the existing layout extends `@section('title')` in each view. Wrap the layout's `<title>` tag so it reads `{{ $branding['brand_name'] }} — @yield('title')` (fall back to `Apex — @yield('title')` when no brand set, via the service default).
- **`/shop`:** same pattern in the shop layout. Where titles are set via Livewire components, expose a `brandedTitle()` helper on the layout component that prepends `$branding['brand_name']` to whatever the page passes in.
- **Email subjects:** out of scope — mail subjects already pass through `Mail::subject()` and tenants can set those independently (existing behavior).

### 6.2 Admin backoffice

`resources/views/layout/app.blade.php` — inside `<head>`:
```blade
<style>
    :root {
        --bs-primary: {{ $branding['primary'] }};
        --bs-primary-active: {{ $branding['secondary'] }};
        --bs-info: {{ $branding['accent'] }};
    }
</style>
```

Sidebar brand partial (path TBD during build, likely `resources/views/layout/layout/partials/aside/_brand.blade.php`):
```blade
@if ($branding['logo_url'])
    <img src="{{ $branding['logo_url'] }}" alt="{{ $branding['brand_name'] }}" class="h-30px">
@else
    <span class="fw-bold fs-3 text-primary">{{ $branding['brand_name'] }}</span>
@endif
```

### 6.3 /shop (Livewire / Blade ecommerce)

Same `:root` CSS-var override pattern in the shop layout. Tailwind v4 `@theme` block in shop CSS references `var(--color-brand-primary)` etc., so the override propagates everywhere `bg-brand` / `text-brand` utilities are used.

Logo swap in the shop navbar partial.

### 6.4 Email templates

`resources/views/vendor/mail/html/header.blade.php` — branded header:
```blade
<a href="{{ $url }}">
    @if (isset($branding['logo_url']) && $branding['logo_url'])
        <img src="{{ $branding['logo_url'] }}" class="logo" alt="{{ $branding['brand_name'] }}">
    @else
        {{ $branding['brand_name'] ?? config('app.name') }}
    @endif
</a>
```

Pass branding to every Mailable by either:
- A `WithBranding` trait that resolves branding in `build()` and adds to view data, OR
- A global Mail composer (preferred — fewer touch points).

Mail CTA button color: override Mail's default blue with `$branding['primary']` via an inline style on the table component (Symfony Mail / Laravel's mail components allow custom theme overrides).

---

## 7. API endpoint for apex_pos

Route in `routes/api/pos.php` (existing `auth:api` group):
```php
Route::get('/branding', [BrandingController::class, 'show'])->name('api.pos.branding');
```

Controller: `App\Http\Controllers\API\v1\pos\BrandingController@show`. Resource: `App\Http\Resources\BrandingResource`. Resolves tenant from authenticated user.

Response:
```json
{
    "data": {
        "palette_key": "ocean_breeze",
        "primary_color": "#1858fd",
        "secondary_color": "#1652ea",
        "accent_color": "#f6a623",
        "on_primary": "#ffffff",
        "on_secondary": "#ffffff",
        "logo_url": "https://leteres.com/storage/branding/12/abc123.png",
        "brand_name": "Quick Baskets",
        "updated_at": "2026-06-05T14:00:00Z"
    }
}
```

`updated_at` is the Flutter cache-key — when it changes, the Flutter client rebuilds its theme.

Tests:
- Authenticated POS user receives 200 + JSON shape.
- Unauthenticated → 401.
- Tenant A's token returns tenant A's branding (never B's).
- Tenant without branding row returns default palette payload + `logo_url: null`.

---

## 8. Files to be created / modified (apex_backend)

### New
- `database/migrations/{ts}_create_color_palettes_table.php`
- `database/migrations/{ts}_create_branding_settings_table.php`
- `database/seeders/ColorPaletteSeeder.php`
- `database/factories/ColorPaletteFactory.php`
- `database/factories/BrandingSettingFactory.php`
- `app/Models/Settings/ColorPalette.php`
- `app/Models/Settings/BrandingSetting.php`
- `app/Observers/ColorPaletteObserver.php`
- `app/Observers/BrandingSettingObserver.php`
- `app/Services/BrandingService.php`
- `app/Http/Controllers/SuperAdmin/ColorPaletteController.php`
- `app/Http/Requests/SuperAdmin/StoreColorPaletteRequest.php`
- `app/Http/Requests/SuperAdmin/UpdateColorPaletteRequest.php`
- `app/Http/Controllers/Admin/Settings/BrandingController.php`
- `app/Http/Requests/Admin/Settings/UpdateBrandingRequest.php`
- `app/Http/Controllers/API/v1/pos/BrandingController.php`
- `app/Http/Resources/BrandingResource.php`
- `app/Http/Middleware/NoSniffHeader.php` — adds `X-Content-Type-Options: nosniff` globally.
- `resources/views/superadmin/color-palettes/index.blade.php`
- `resources/views/superadmin/color-palettes/create.blade.php`
- `resources/views/superadmin/color-palettes/edit.blade.php`
- `resources/views/superadmin/color-palettes/_form.blade.php`
- `resources/views/admin/settings/branding/index.blade.php`
- `tests/Feature/SuperAdmin/ColorPaletteControllerTest.php`
- `tests/Feature/Admin/Settings/BrandingControllerTest.php`
- `tests/Feature/API/v1/pos/BrandingApiTest.php`
- `tests/Unit/Services/BrandingServiceTest.php`

### Modified
- `routes/superadmin.php` — palette CRUD routes.
- `routes/admin.php` — `/admin/settings/branding` routes.
- `routes/api/pos.php` — `/branding` endpoint.
- `bootstrap/app.php` — register `NoSniffHeader` middleware globally; register observers.
- `app/Providers/AppServiceProvider.php` — register the branding View Composer.
- `resources/views/layout/app.blade.php` — `:root` CSS-var injection.
- `resources/views/layout/layout/partials/aside/_brand.blade.php` (path TBD) — sidebar logo swap.
- `resources/views/layout/layout/partials/sidebar/_menu.blade.php` — "Branding" tenant sidebar item.
- `resources/views/superadmin/_layout.blade.php` (or wherever superadmin menu lives) — "Color Palettes" sidebar item.
- Shop layout (path TBD) — `:root` injection + logo swap.
- Shop Tailwind CSS — `@theme` block references CSS vars.
- `resources/views/vendor/mail/html/header.blade.php` — branded header.

---

## 9. Tests (≥18)

| Test | Asserts |
|---|---|
| `ColorPaletteControllerTest::test_superadmin_can_list_palettes` | 200 + DataTable shape |
| `ColorPaletteControllerTest::test_superadmin_can_create_palette` | DB row created |
| `ColorPaletteControllerTest::test_validation_rejects_non_hex_colors` | 422 |
| `ColorPaletteControllerTest::test_validation_rejects_duplicate_key` | 422 |
| `ColorPaletteControllerTest::test_cannot_delete_default_palette` | 422 with message |
| `ColorPaletteControllerTest::test_set_default_clears_previous_default` | single default invariant |
| `ColorPaletteControllerTest::test_non_superadmin_cannot_access` | redirect / 403 |
| `BrandingControllerTest::test_admin_can_view_branding_page` | 200 + palettes rendered |
| `BrandingControllerTest::test_admin_can_save_palette_selection` | DB row updated + cache invalidated |
| `BrandingControllerTest::test_admin_can_upload_png_logo` | File stored under tenant scope |
| `BrandingControllerTest::test_admin_can_upload_webp_logo` | File stored |
| `BrandingControllerTest::test_admin_cannot_upload_svg` | 422 |
| `BrandingControllerTest::test_admin_cannot_upload_oversize_logo` | 422 |
| `BrandingControllerTest::test_admin_cannot_overwrite_another_tenants_branding` | 403 / boundary holds |
| `BrandingControllerTest::test_validation_rejects_brand_name_with_html` | 422 |
| `BrandingApiTest::test_authenticated_returns_branding` | 200 + JSON shape |
| `BrandingApiTest::test_unauthenticated_returns_401` | 401 |
| `BrandingApiTest::test_returns_default_when_tenant_has_no_setting` | default palette payload |
| `BrandingServiceTest::test_falls_back_to_default_when_palette_inactive` | default colors returned |
| `BrandingServiceTest::test_cache_invalidates_on_save` | different result pre/post save |
| `BrandingServiceTest::test_sanitize_hex_rejects_css_injection_attempt` | tampered DB row gracefully handled |

---

## 10. Phasing within the PR

1. Migrations + models + observers + factories.
2. `ColorPaletteSeeder` with ~10 starter palettes.
3. `BrandingService` + unit tests.
4. SuperAdmin Color Palette CRUD + tests.
5. Admin Branding page + tests (upload, validation, tenant boundary).
6. View Composer + admin layout CSS injection + logo swap.
7. /shop layout CSS injection + logo swap.
8. Email header branded.
9. API endpoint + Resource + tests.
10. `NoSniffHeader` middleware globally registered.
11. Pint, full test suite, manual `/shop` + `/admin` + `/superadmin/color-palettes` smoke.

Single feature branch → `dev` → `main`. Pre-merge: SuperAdmin manually approves the curated palette list looks aesthetically right.

---

## 11. Open questions (residual — resolved during build)

- Shop layout file path — confirm during build by `grep`-ing the existing ecommerce layout.
- Sidebar brand partial path — same.

---

## 12. apex_pos integration

apex_pos consumes `GET /api/v1/branding` and applies the returned palette + logo. **It must always follow the backend** — palette keys, colour values, and logo URLs are authoritative.

The Flutter-side implementation is spec'd in [`apex_pos_spec.md`](./apex_pos_spec.md), to be executed by a separate agent against the `apex_pos` repository. That spec is informed by a structural scan of `../apex_pos` and will land alongside this plan.

---

## 13. Out of scope for v1

- Per-store branding (different stores under one tenant). Tenant-level only.
- Dark-mode variants per palette.
- Customer-facing brand customization (loyalty program logos, etc.).
- Custom font upload.
- A/B testing of palettes.
- Signed/private logo URLs.
- CSP nonce-based hardening (replaces inline `<style>` with a dedicated `/branding-{tenant}.css` route — future work).
- Public webhook for "branding updated" pushing to apex_pos (poll on `updated_at` instead).
