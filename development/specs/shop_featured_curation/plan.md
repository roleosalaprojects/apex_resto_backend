# Shop Featured Curation — Plan

**Status:** Planning. Not yet approved to build.
**Date:** 2026-06-06
**Owner:** Richard
**Scope:** apex_backend — admin curation surface + storefront rendering for Featured Categories and Featured Products on `/shop`.
**Companion plan:** This is the user-queued follow-up to `tenant_branding/plan.md`. The two are independent (no shared schema or controllers); they can ship in either order.

---

## Problem

The `/shop` homepage currently renders **every active category** in a "Shop by Category" grid (~25 cards including "NO CATEGORY" and zero-item "diapers"). That's noise, not curation — it pushes important categories below the fold and signals nothing about what the tenant wants customers to see first. There is also no "Featured Products" surface today — every product reaches customers only through search, browse, or category drill-down.

## Goal

Give tenant admins **manual** control over what's spotlighted on `/shop`:

1. A curated **Featured Categories** strip on the homepage (replaces today's all-categories grid as the *spotlight*; full list still reachable via "View All" / the navbar dropdown).
2. A new **Featured Products** section between the hero and the categories strip on the homepage.
3. Sensible fallback when nothing is featured — page never goes blank.

---

## Confirmed decisions (locked 2026-06-06)

1. **Curation UX:** **Dedicated curation page** at `/admin/shop/curation` with two tabs (Featured Categories, Featured Products). Side-by-side layout: featured list on the left (drag handles + Remove button), search/add panel on the right. No per-record toggle on edit forms.
2. **Sort order:** **Drag-and-drop**. Persists to a `featured_order` unsignedInt column on drag-end via an AJAX `POST /admin/shop/curation/{type}/reorder` endpoint that receives an ordered list of IDs and rewrites the column atomically in a transaction.
3. **Fallback when nothing featured:** **All active**, ordered by name. Page never goes blank.
4. **Scope:** **Homepage only.** Navbar Categories dropdown and `/shop/products` sidebar stay full.
5. **Max displayed:** **12 per type** (hard cap on render — admin can mark more, the overflow waits its turn). Curation page UI shows a "12 of N featured" counter.
6. **`featured_order` collisions:** allowed; tiebreaker is most-recently-set-featured first.
7. **Tenant scoping:** per-tenant via existing `user_id` column on both tables.
8. **Branching:** **`feature/shop-featured-curation` off `dev`, in parallel with `feature/tenant-branding`.** Note the merge-conflict risk on `resources/views/ecommerce/index.blade.php` — both branches edit it. Whichever lands second resolves the conflict (low-effort, no overlap in logic).

---

## Architecture

### 1. Data model

**`categories` table — additive migration:**

| column | type | notes |
|---|---|---|
| `featured` | boolean, default false | Whether to spotlight on `/shop` homepage. |
| `featured_order` | unsignedInteger, nullable | Sort key within featured (lower = earlier). Null = unsorted, appears after numbered ones. |

**`items` table — additive migration:**

| column | type | notes |
|---|---|---|
| `featured` | boolean, default false | Whether to spotlight on `/shop` homepage. |
| `featured_order` | unsignedInteger, nullable | Sort key within featured (lower = earlier). |

**Why a NEW field on `items` instead of reusing `items.priority`:**

`items.priority` already exists and is owned by the SuperAdmin Priority Items surface (admin dashboard widget, live-sales-count). Overloading `priority` for storefront featuring would couple two unrelated decisions (which items to watch internally vs. which items to spotlight publicly). Keep them separate.

### 2. Models / scopes

`App\Models\Products\Category`:

```php
public function scopeFeaturedSpotlight($query)
{
    return $query
        ->where('featured', true)
        ->where('status', true)
        ->orderByRaw('featured_order IS NULL, featured_order ASC')
        ->orderBy('name');
}
```

Same pattern on `App\Models\Products\Item`. The `orderByRaw` puts `NULL` orders last; numbered orders sort ascending; alphabetical/name tiebreaker.

### 3. Admin UX (v1)

**On the category edit page (`resources/views/admin/products/categories/_form.blade.php` or equivalent):**

- New card "Storefront Spotlight" with:
  - "Feature on /shop homepage" toggle (binds `featured`)
  - "Display order" numeric input (binds `featured_order`, helper text: "Lower numbers appear first; leave blank to push to the end")
- Persisted by the existing `CategoryController@update` via Form Request validation.

**On the item edit page (`resources/views/admin/products/items/_fields.blade.php`):**

- Identical "Storefront Spotlight" card.
- Persisted via the existing `ItemController@update` flow.

**Form Request validation rules added to both `UpdateCategoryRequest` and the item update path:**

```php
'featured' => ['nullable', 'boolean'],
'featured_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
```

### 4. Storefront rendering

**Homepage (`routes/ecommerce.php` shop home closure + `resources/views/ecommerce/index.blade.php`):**

The closure currently passes `$categories = all active categories with item counts`. Replace with:

```php
$featuredCategories = Category::featuredSpotlight()
    ->withCount('items')
    ->limit(12)
    ->get();

// Fallback: if nothing featured, show first 12 active categories
// so the page never goes blank during initial adoption.
$spotlightCategories = $featuredCategories->isEmpty()
    ? Category::where('status', true)->withCount('items')->limit(12)->get()
    : $featuredCategories;

$featuredItems = Item::featuredSpotlight()
    ->where('status', true)
    ->limit(12)
    ->get();
```

The view renders **two sections** between the hero and the existing layout:

```
Hero (existing)
↓
[NEW] Featured Products — horizontal scroll or grid of product cards
↓
Shop by Category — now bound to $spotlightCategories instead of all
↓
Why Choose Us (existing)
↓
... rest of homepage
```

A "View All" link at the top of each section preserves access to the full list (already exists for Categories; add for Products → `/shop/products`).

**What does NOT change in v1:**
- Navbar Categories dropdown (still shows all via EcommerceComposer)
- `/shop/products` page sidebar / filter (still shows all)
- Search results (already full-corpus)

### 5. Fallback behavior

| State | Featured Categories shows | Featured Products shows |
|---|---|---|
| Nothing featured yet | First 12 active categories (current behavior) | Hidden — no products section appears |
| 1–12 featured | Just those, in order | Just those, in order |
| 13+ featured | First 12 by `featured_order` | First 12 by `featured_order` |

**Why "hidden" for Products but "fallback" for Categories:** the homepage currently shows a Categories grid, so a sudden empty state would be a regression. There IS no current Products section, so hiding it until populated is the no-regression path.

### 6. Image / icon presentation

Categories already have `icon` (emoji) and `image` (url) fields. Featured Categories grid keeps current rendering. **No new image fields v1.**

Items currently have `image`. Featured Products grid uses existing item image with a fallback (likely a placeholder SVG — confirm during build).

---

## Files to be created / modified

### New
- `database/migrations/{ts}_add_featured_to_categories_table.php`
- `database/migrations/{ts}_add_featured_to_items_table.php`
- `tests/Feature/Admin/Products/CategoryFeaturedTest.php`
- `tests/Feature/Admin/Products/ItemFeaturedTest.php`
- `tests/Feature/Ecommerce/ShopHomeFeaturedTest.php`

### Modified
- `app/Models/Products/Category.php` — add `featured` + `featured_order` to `$fillable` and casts, add `scopeFeaturedSpotlight` scope
- `app/Models/Products/Item.php` — same shape
- `app/Http/Controllers/Admin/Products/CategoryController.php` — pass through new fields on update
- `app/Http/Requests/Admin/Products/UpdateCategoryRequest.php` (or inline rules) — add validation
- `app/Http/Controllers/Admin/Products/ItemController.php` — pass through new fields on update
- Item update request rules — add validation
- `resources/views/admin/products/categories/_form.blade.php` (or equivalent) — Storefront Spotlight card
- `resources/views/admin/products/items/_fields.blade.php` — Storefront Spotlight card
- `routes/ecommerce.php` — replace inline category fetch with featured + fallback logic, add `$featuredItems`
- `resources/views/ecommerce/index.blade.php` — rename `$categories` references to `$spotlightCategories`; add new Featured Products section between hero and categories grid; reuse `var(--qb-primary-rgb)` tokens for any new shadows (consistent with the branding work)

---

## Tests (≥10)

| Test | Asserts |
|---|---|
| `CategoryFeaturedTest::test_admin_can_toggle_featured_on_category_via_edit` | DB row updated |
| `CategoryFeaturedTest::test_validation_rejects_negative_featured_order` | 422 |
| `ItemFeaturedTest::test_admin_can_toggle_featured_on_item_via_edit` | DB row updated |
| `ItemFeaturedTest::test_validation_rejects_negative_featured_order` | 422 |
| `ShopHomeFeaturedTest::test_homepage_shows_only_featured_categories_when_some_are_marked` | Only featured cards present |
| `ShopHomeFeaturedTest::test_homepage_orders_featured_categories_by_featured_order` | Render order matches |
| `ShopHomeFeaturedTest::test_homepage_falls_back_to_all_active_when_none_featured` | Behaves like today |
| `ShopHomeFeaturedTest::test_homepage_hides_products_section_when_no_items_featured` | Section absent |
| `ShopHomeFeaturedTest::test_homepage_shows_featured_products_when_marked` | Featured items render |
| `ShopHomeFeaturedTest::test_navbar_categories_dropdown_still_shows_all_categories` | Featured filter does NOT leak to navbar |
| `ShopHomeFeaturedTest::test_inactive_category_marked_featured_is_excluded` | `status=false` overrides `featured=true` |

---

## Phasing within the PR

1. Migrations (categories + items featured fields).
2. Model fillable/casts/scope updates.
3. Category edit form + controller update + Form Request validation + test.
4. Item edit form + controller update + Form Request validation + test.
5. Shop home route + view updates (Featured Products section + spotlight binding) + tests.
6. Pint + targeted regression on shop home + category/item tests.

Single feature branch (`feature/shop-featured-curation`), merge to `dev`, then `main`. **Branched off `dev` after `feature/tenant-branding` lands** so the tenant-branding CSS tokens (`--qb-primary-rgb` etc.) are already available — the Featured sections will use them for any styled shadows.

---

## Out of scope for v1 (parking lot)

- Auto-popular fallback (top N by sales last 30 days) — needs sales aggregation pipeline; revisit when "fall back to all active" feels stale.
- Schedule-based featuring (`featured_starts_at`, `featured_ends_at`) — useful for promo cycles but adds complexity.
- Dedicated curation page (`/admin/shop/curation`) with drag-and-drop — easy to layer on once per-record toggles prove the concept.
- Featured-by-store (different storefront per location) — multi-store storefront isn't a current architectural concern.
- Featured image per category, distinct from the existing `image` field — `image` already covers it.
- Auto-feature when item priority is set — keeping `priority` and `featured` separate per §1 of Data model.
- Featured in `/shop/products` page sidebar (e.g. "Featured filter chip at top of filter list") — could come later if homepage adoption proves the pattern.
- Per-tenant override of fallback behavior (one tenant wants "hide" instead of "all active") — current default is reasonable for everyone v1.

---

## Open questions

- Confirm category edit view path (`_form.blade.php` vs `_fields.blade.php`) — grep during build.
- Confirm Item update Form Request name — `UpdateItemRequest` or inline rules in `ItemController@update`.
- Featured Products card layout: copy the existing product-card component from `/shop/products`, or a slimmer "spotlight" card variant? Recommendation: reuse existing component for visual consistency; cap items at 12.
