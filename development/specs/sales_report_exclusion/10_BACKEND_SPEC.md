# Sales Report Exclusion System - Backend Specification

## 1.0 Overview

This document specifies the backend implementation (Laravel 12) for the Sales Report Exclusion System. All core logic, data filtering, and API endpoints reside in the backend.

**Component:** `apex_backend`  
**Technology:** Laravel 12, PHP 8.4, MySQL 8  
**Estimated Effort:** 55-60 hours

---

## 1.1 Database Schema

### 1.1.1 New Table: `sales_report_excluded_items`

**File:** `database/migrations/YYYY_MM_DD_create_sales_report_excluded_items_table.php`

```php
Schema::create('sales_report_excluded_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
    $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
    $table->string('reason')->nullable();
    $table->boolean('is_active')->default(true);
    $table->foreignId('created_by')->constrained('admins')->nullOnDelete();
    $table->foreignId('updated_by')->nullable()->constrained('admins')->nullOnDelete();
    $table->timestamps();
    $table->softDeletes();

    $table->unique(['item_id', 'store_id']);
});
```

**Fields:**
- `item_id`: Foreign key to items table
- `store_id`: Foreign key to stores table (exclusion is per-store)
- `reason`: Optional reason for exclusion (audit trail)
- `is_active`: Soft enable/disable for individual items
- `created_by`: Admin who created the exclusion
- `updated_by`: Admin who last modified the exclusion
- Unique constraint: One exclusion record per item per store

---

### 1.1.2 Modify Table: `business_settings`

**File:** `database/migrations/YYYY_MM_DD_add_sales_report_exclusion_to_business_settings.php`

```php
Schema::table('business_settings', function (Blueprint $table) {
    $table->boolean('sales_report_exclusion_enabled')->default(false)->after('value');
    $table->boolean('sales_report_exclusion_show_original')->default(false)->after('sales_report_exclusion_enabled');
});
```

**Fields:**
- `sales_report_exclusion_enabled`: Global toggle for the exclusion feature
- `sales_report_exclusion_show_original`: Toggle to view original vs filtered data

---

### 1.1.3 New Table: `sales_report_exclusion_state_history`

**File:** `database/migrations/YYYY_MM_DD_create_sales_report_exclusion_state_history_table.php`

```php
Schema::create('sales_report_exclusion_state_history', function (Blueprint $table) {
    $table->id();
    $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
    $table->boolean('exclusion_enabled')->default(false);
    $table->boolean('show_original')->default(false);
    $table->json('excluded_item_ids')->nullable();
    $table->timestamps();

    $table->index('sale_id');
    $table->index('created_at');
});
```

**Fields:**
- `sale_id`: Foreign key to the sale
- `exclusion_enabled`: Whether exclusion was enabled at time of sale
- `show_original`: Whether show_original was enabled at time of sale
- `excluded_item_ids`: JSON array of item IDs that were excluded at time of sale

---

## 1.2 Models

### 1.2.1 `SalesReportExcludedItem`

**File:** `app/Models/Settings/SalesReportExcludedItem.php`

```php
<?php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesReportExcludedItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'item_id',
        'store_id',
        'reason',
        'is_active',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function item()
    {
        return $this->belongsTo(\App\Models\Products\Item::class);
    }

    public function store()
    {
        return $this->belongsTo(\App\Models\Settings\Store::class);
    }

    public function creator()
    {
        return $this->belongsTo(\App\Models\Admin::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(\App\Models\Admin::class, 'updated_by');
    }

    /**
     * Scope: Get active exclusions for a specific store
     */
    public function scopeActiveForStore($query, $storeId)
    {
        return $query->where('store_id', $storeId)
            ->where('is_active', true);
    }

    /**
     * Scope: Get exclusions for a specific store (active and inactive)
     */
    public function scopeForStore($query, $storeId)
    {
        return $query->where('store_id', $storeId);
    }
}
```

---

### 1.2.2 `SalesReportExclusionStateHistory`

**File:** `app/Models/Settings/SalesReportExclusionStateHistory.php`

```php
<?php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesReportExclusionStateHistory extends Model
{
    use HasFactory;

    protected $table = 'sales_report_exclusion_state_history';

    protected $fillable = [
        'sale_id',
        'exclusion_enabled',
        'show_original',
        'excluded_item_ids',
    ];

    protected $casts = [
        'exclusion_enabled' => 'boolean',
        'show_original' => 'boolean',
        'excluded_item_ids' => 'array',
    ];

    public function sale()
    {
        return $this->belongsTo(\App\Models\Pos\Sale::class);
    }
}
```

---

### 1.2.3 Modify `Sale` Model

**File:** `app/Models/Pos/Sale.php`

Add the following to the Sale model:

```php
// Add to imports
use App\Traits\SalesReportExclusionTrait;

class Sale extends Model
{
    use SalesReportExclusionTrait;
    // ... existing code

    /**
     * Get the Sales Report Exclusion state at the time of this sale
     */
    public function getSalesReportExclusionStateAtSaleAttribute()
    {
        $historical = $this->salesReportExclusionHistoricalState()->first();

        if ($historical) {
            return [
                'exclusion_enabled' => $historical->exclusion_enabled,
                'show_original' => $historical->show_original,
                'excluded_item_ids' => $historical->excluded_item_ids ?? [],
            ];
        }

        // Fallback to current settings if no history
        return [
            'exclusion_enabled' => $this->isSalesReportExclusionEnabled(),
            'show_original' => $this->isShowingOriginal(),
            'excluded_item_ids' => [],
        ];
    }

    /**
     * Relationship to historical state
     */
    public function salesReportExclusionHistoricalState()
    {
        return $this->hasOne(SalesReportExclusionStateHistory::class, 'sale_id');
    }

    /**
     * Get sale lines filtered for Sales Report reporting
     */
    public function saleLinesForSalesReport()
    {
        $salesReportExclusionService = app(\App\Services\SalesReportExclusionService::class);
        
        // Get the state at time of sale
        $state = $this->salesReportExclusionStateAtSale;
        
        // If exclusion was disabled or we should show original, return all lines
        if (!$state['exclusion_enabled'] || $state['show_original']) {
            return $this->saleLines();
        }

        // Filter out excluded items
        return $this->saleLines()->whereNotIn('item_id', $state['excluded_item_ids']);
    }

    /**
     * Calculate Sales Report-adjusted total
     */
    public function getSalesReportTotalAttribute()
    {
        return $this->saleLinesForSalesReport()->sum('total');
    }

    /**
     * Calculate Sales Report-adjusted VATable sales
     */
    public function getSalesReportVatableSalesAttribute()
    {
        return $this->saleLinesForSalesReport()
            ->where('vat_type', '!=', 'exempt')
            ->sum(function($line) {
                return $line->price * $line->quantity;
            });
    }

    /**
     * Calculate Sales Report-adjusted VAT
     */
    public function getSalesReportVatAttribute()
    {
        return $this->saleLinesForSalesReport()
            ->where('vat_type', '!=', 'exempt')
            ->sum('vat_amount');
    }

    /**
     * Calculate Sales Report-adjusted VAT exempt
     */
    public function getSalesReportVatExemptAttribute()
    {
        return $this->saleLinesForSalesReport()
            ->where('vat_type', 'exempt')
            ->sum('total');
    }
}
```

---

### 1.2.4 Modify `BusinessSettings` Model

**File:** `app/Models/BusinessSettings.php`

```php
// Add to casts
protected $casts = [
    'sales_report_exclusion_enabled' => 'boolean',
    'sales_report_exclusion_show_original' => 'boolean',
];

// Add helper methods
public static function isSalesReportExclusionEnabled()
{
    return self::get('sales_report_exclusion_enabled', false);
}

public static function shouldShowOriginal()
{
    return self::get('sales_report_exclusion_show_original', false);
}
```

---

## 1.3 Trait: SalesReportExclusionTrait

**File:** `app/Traits/SalesReportExclusionTrait.php`

```php
<?php

namespace App\Traits;

use App\Models\Settings\SalesReportExcludedItem;

trait SalesReportExclusionTrait
{
    /**
     * Apply Sales Report Exclusion filtering to query
     *
     * This scope filters out items that are excluded from Sales Report reporting
     * based on the current or historical state.
     */
    public function scopeExcludeForSalesReport($query)
    {
        // Get the service
        $salesReportExclusionService = app(\App\Services\SalesReportExclusionService::class);
        
        // If exclusion is disabled or we should show original, return unfiltered
        if (!$salesReportExclusionService->isEnabled() || $salesReportExclusionService->shouldShowOriginal()) {
            return $query;
        }

        // Get current store ID from the model or context
        $storeId = $this->getStoreIdForContext();
        
        if (!$storeId) {
            return $query;
        }

        // Get excluded item IDs
        $excludedIds = $salesReportExclusionService->getExcludedItemIds($storeId);
        
        if (empty($excludedIds)) {
            return $query;
        }

        // Filter out excluded items
        return $query->whereNotIn('item_id', $excludedIds);
    }

    /**
     * Get the store ID from the model context
     */
    protected function getStoreIdForContext()
    {
        // If this model has a store_id attribute, use it
        if (isset($this->store_id)) {
            return $this->store_id;
        }

        // If this is a Sale model, use its store_id
        if (property_exists($this, 'store_id')) {
            return $this->store_id;
        }

        // Fallback: try to get from request or session
        if (request()->has('store_id')) {
            return request('store_id');
        }

        return null;
    }

    /**
     * Check if Sales Report Exclusion is currently enabled
     */
    public function isSalesReportExclusionEnabled()
    {
        return app(\App\Services\SalesReportExclusionService::class)->isEnabled();
    }

    /**
     * Check if showing original (unfiltered) data
     */
    public function isShowingOriginal()
    {
        return app(\App\Services\SalesReportExclusionService::class)->shouldShowOriginal();
    }

    /**
     * Get excluded item IDs for a store
     */
    public function getExcludedItemIds($storeId)
    {
        return app(\App\Services\SalesReportExclusionService::class)->getExcludedItemIds($storeId);
    }
}
```

---

## 1.4 Service: SalesReportExclusionService

**File:** `app/Services/SalesReportExclusionService.php`

```php
<?php

namespace App\Services;

use App\Models\BusinessSettings;
use App\Models\Settings\SalesReportExcludedItem;
use App\Models\Pos\Sale;
use App\Models\Pos\Zreading;
use App\Models\Pos\Xreading;

class SalesReportExclusionService
{
    /**
     * Check if Sales Report Exclusion is currently enabled
     */
    public function isEnabled(): bool
    {
        return BusinessSettings::get('sales_report_exclusion_enabled', false);
    }

    /**
     * Check if showing original (unfiltered) data
     */
    public function shouldShowOriginal(): bool
    {
        return BusinessSettings::get('sales_report_exclusion_show_original', false);
    }

    /**
     * Check if an item is excluded for a store
     */
    public function isItemExcluded(int $itemId, int $storeId): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        return SalesReportExcludedItem::activeForStore($storeId)
            ->where('item_id', $itemId)
            ->exists();
    }

    /**
     * Get all excluded item IDs for a store
     */
    public function getExcludedItemIds(int $storeId): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        return SalesReportExcludedItem::activeForStore($storeId)
            ->pluck('item_id')
            ->toArray();
    }

    /**
     * Get a sale with Sales Report Exclusion filtering applied
     *
     * This respects the historical state at the time of sale
     */
    public function getSaleForSalesReport(Sale $sale): Sale
    {
        $state = $sale->salesReportExclusionStateAtSale;

        // If we should show original, return the sale as-is
        if ($state['show_original']) {
            return $sale;
        }

        // If exclusion was disabled at time of sale, return as-is
        if (!$state['exclusion_enabled']) {
            return $sale;
        }

        // Create a new sale instance with filtered lines
        $filteredSale = $sale->replicate();
        
        // Get original lines and filter them
        $filteredLines = $sale->saleLines->filter(function($line) use ($state) {
            return !in_array($line->item_id, $state['excluded_item_ids'] ?? []);
        })->values();

        // Set the filtered lines as a relation
        $filteredSale->setRelation('saleLines', $filteredLines);

        return $filteredSale;
    }

    /**
     * Get a reading with Sales Report Exclusion filtering applied
     */
    public function getReadingForSalesReport($reading)
    {
        $showOriginal = $this->shouldShowOriginal();

        // If showing original, return as-is
        if ($showOriginal) {
            return $reading;
        }

        // For readings, we need to recalculate based on filtered sales
        // This is more complex - the reading was generated with filtering
        // so it already has filtered values
        
        // If the reading was generated when exclusion was enabled,
        // it already has the filtered values. We just return it.
        // If exclusion was disabled at generation time, we need to filter.
        
        // For now, assume readings store both original and filtered
        // Or we can recalculate from the associated sales
        
        return $reading;
    }

    /**
     * Check if an item should be excluded in the current context
     *
     * This is used for real-time checks (e.g., when creating a new sale)
     */
    public function shouldExcludeItem(int $itemId, int $storeId): bool
    {
        if (!$this->isEnabled() || $this->shouldShowOriginal()) {
            return false;
        }

        return $this->isItemExcluded($itemId, $storeId);
    }
}
```

---

## 1.5 Controllers

### 1.5.1 Superadmin: SalesReportExclusionController

**File:** `app/Http/Controllers/SuperAdmin/SalesReportExclusionController.php`

```php
<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Models\BusinessSettings;
use App\Models\Products\Item;
use App\Models\Settings\SalesReportExcludedItem;
use Illuminate\Http\Request;

class SalesReportExclusionController extends Controller
{
    /**
     * Toggle the Sales Report Exclusion feature
     */
    public function toggle(Request $request)
    {
        $validated = $request->validate([
            'enabled' => 'required|boolean'
        ]);

        BusinessSettings::updateOrCreate(
            ['key' => 'sales_report_exclusion_enabled'],
            ['value' => $validated['enabled']]
        );

        return response()->json([
            'success' => true,
            'enabled' => $validated['enabled']
        ]);
    }

    /**
     * Toggle the show original flag
     */
    public function toggleShowOriginal(Request $request)
    {
        $validated = $request->validate([
            'show_original' => 'required|boolean'
        ]);

        BusinessSettings::updateOrCreate(
            ['key' => 'sales_report_exclusion_show_original'],
            ['value' => $validated['show_original']]
        );

        return response()->json([
            'success' => true,
            'show_original' => $validated['show_original']
        ]);
    }

    /**
     * List excluded items (with datatable support)
     */
    public function index(Request $request)
    {
        $query = SalesReportExcludedItem::with(['item', 'store', 'creator'])
            ->orderBy('created_at', 'desc');

        if ($request->has('store_id')) {
            $query->where('store_id', $request->store_id);
        }

        if ($request->has('search')) {
            $query->whereHas('item', function($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%')
                  ->orWhere('barcode', 'like', '%'.$request->search.'%');
            });
        }

        return $query->paginate($request->per_page ?? 25);
    }

    /**
     * Add an item to the exclusion list
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'item_id' => 'required|exists:items,id',
            'store_id' => 'required|exists:stores,id',
            'reason' => 'nullable|string|max:255',
        ]);

        $validated['created_by'] = auth('admin')->id();

        // Check if already exists (including soft-deleted)
        $existing = SalesReportExcludedItem::withTrashed()
            ->where('item_id', $validated['item_id'])
            ->where('store_id', $validated['store_id'])
            ->first();

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
            }
            $existing->update($validated);
            $excluded = $existing;
        } else {
            $excluded = SalesReportExcludedItem::create($validated);
        }

        return response()->json([
            'success' => true,
            'message' => 'Item ' . ($existing ? 'updated' : 'added') . ' successfully',
            'data' => $excluded->load(['item', 'store'])
        ]);
    }

    /**
     * Update an excluded item
     */
    public function update(Request $request, SalesReportExcludedItem $salesReportExcludedItem)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $validated['updated_by'] = auth('admin')->id();

        $salesReportExcludedItem->update($validated);

        return response()->json([
            'success' => true,
            'data' => $salesReportExcludedItem->load(['item', 'store'])
        ]);
    }

    /**
     * Delete an excluded item
     */
    public function destroy(SalesReportExcludedItem $salesReportExcludedItem)
    {
        $salesReportExcludedItem->delete();

        return response()->json([
            'success' => true,
            'message' => 'Item removed from exclusion list'
        ]);
    }

    /**
     * Toggle a single item's active status
     */
    public function toggleItem(SalesReportExcludedItem $salesReportExcludedItem)
    {
        $salesReportExcludedItem->update([
            'is_active' => !$salesReportExcludedItem->is_active,
            'updated_by' => auth('admin')->id()
        ]);

        return response()->json([
            'success' => true,
            'is_active' => $salesReportExcludedItem->fresh()->is_active
        ]);
    }

    /**
     * Check if an item is excluded (for backend API)
     */
    public function check(Item $item, Request $request)
    {
        $storeId = $request->store_id;
        $salesReportExclusionService = app(\App\Services\SalesReportExclusionService::class);

        if (!$storeId) {
            return response()->json([
                'is_excluded' => false,
                'error' => 'store_id is required'
            ]);
        }

        return response()->json([
            'is_excluded' => $salesReportExclusionService->isItemExcluded($item->id, $storeId),
            'item_id' => $item->id,
            'store_id' => $storeId
        ]);
    }
}
```

---

### 1.5.2 POS API: SalesReportExclusionController

**File:** `app/Http/Controllers/API/v1/pos/SalesReportExclusionController.php`

```php
<?php

namespace App\Http\Controllers\API\v1\pos;

use App\Models\Products\Item;
use App\Services\SalesReportExclusionService;
use Illuminate\Http\Request;

class SalesReportExclusionController extends Controller
{
    /**
     * Check if an item is excluded for the current store
     */
    public function check(Item $item, Request $request)
    {
        $storeId = $request->store_id;
        $salesReportExclusionService = app(SalesReportExclusionService::class);

        if (!$storeId) {
            return response()->json([
                'success' => false,
                'message' => 'store_id is required',
                'is_excluded' => false
            ]);
        }

        return response()->json([
            'success' => true,
            'is_excluded' => $salesReportExclusionService->isItemExcluded($item->id, $storeId),
            'item' => [
                'id' => $item->id,
                'name' => $item->name,
                'barcode' => $item->barcode,
            ]
        ]);
    }

    /**
     * Get current Sales Report Exclusion settings
     */
    public function settings(Request $request)
    {
        $salesReportExclusionService = app(SalesReportExclusionService::class);

        return response()->json([
            'success' => true,
            'exclusion_enabled' => $salesReportExclusionService->isEnabled(),
            'show_original' => $salesReportExclusionService->shouldShowOriginal(),
        ]);
    }
}
```

---

## 1.6 Observers

### 1.6.1 SaleObserver

**File:** `app/Observers/SaleObserver.php`

```php
<?php

namespace App\Observers;

use App\Models\Pos\Sale;
use App\Models\Settings\SalesReportExclusionStateHistory;
use App\Services\SalesReportExclusionService;

class SaleObserver
{
    /**
     * Handle the Sale "created" event.
     */
    public function created(Sale $sale): void
    {
        $this->captureSalesReportExclusionState($sale);
    }

    /**
     * Handle the Sale "updated" event.
     *
     * Note: We don't update the historical state on update
     * as we want to preserve the state at creation time
     */
    public function updated(Sale $sale): void
    {
        // Don't update historical state on sale update
    }

    /**
     * Capture the Sales Report Exclusion state at the time of sale creation
     */
    protected function captureSalesReportExclusionState(Sale $sale): void
    {
        $salesReportExclusionService = app(SalesReportExclusionService::class);

        // Get excluded item IDs for this store
        $excludedIds = $salesReportExclusionService->getExcludedItemIds($sale->store_id);

        SalesReportExclusionStateHistory::create([
            'sale_id' => $sale->id,
            'exclusion_enabled' => $salesReportExclusionService->isEnabled(),
            'show_original' => $salesReportExclusionService->shouldShowOriginal(),
            'excluded_item_ids' => $excludedIds,
        ]);
    }
}
```

**Register Observer in AppServiceProvider:**

```php
// app/Providers/AppServiceProvider.php
public function boot()
{
    Sale::observe(\App\Observers\SaleObserver::class);
}
```

---

## 1.7 Routes

### 1.7.1 Superadmin Routes

**File:** `routes/superadmin.php` (add to existing file)

```php
// Add to existing route group
Route::prefix('sales-report-exclusion')->middleware(['auth:admin'])->group(function () {
    // Toggle endpoints
    Route::post('/toggle', [\App\Http\Controllers\SuperAdmin\SalesReportExclusionController::class, 'toggle'])
        ->name('superadmin.sales-report-exclusion.toggle');

    Route::post('/toggle-show-original', [\App\Http\Controllers\SuperAdmin\SalesReportExclusionController::class, 'toggleShowOriginal'])
        ->name('superadmin.sales-report-exclusion.toggle-show-original');

    // Excluded items CRUD
    Route::get('/items', [\App\Http\Controllers\SuperAdmin\SalesReportExclusionController::class, 'index'])
        ->name('superadmin.sales-report-exclusion.items.index');

    Route::post('/items', [\App\Http\Controllers\SuperAdmin\SalesReportExclusionController::class, 'store'])
        ->name('superadmin.sales-report-exclusion.items.store');

    Route::put('/items/{salesReportExcludedItem}', [\App\Http\Controllers\SuperAdmin\SalesReportExclusionController::class, 'update'])
        ->name('superadmin.sales-report-exclusion.items.update');

    Route::delete('/items/{salesReportExcludedItem}', [\App\Http\Controllers\SuperAdmin\SalesReportExclusionController::class, 'destroy'])
        ->name('superadmin.sales-report-exclusion.items.destroy');

    Route::post('/items/{salesReportExcludedItem}/toggle', [\App\Http\Controllers\SuperAdmin\SalesReportExclusionController::class, 'toggleItem'])
        ->name('superadmin.sales-report-exclusion.items.toggle');

    // Check endpoint (for internal use)
    Route::get('/items/check/{item}', [\App\Http\Controllers\SuperAdmin\SalesReportExclusionController::class, 'check'])
        ->name('superadmin.sales-report-exclusion.items.check');
});
```

---

### 1.7.2 POS API Routes

**File:** `routes/api/pos.php` (add to existing file)

```php
// Add inside the auth:api middleware group
Route::prefix('sales-report-exclusion')->group(function () {
    Route::get('/settings', [\App\Http\Controllers\API\v1\pos\SalesReportExclusionController::class, 'settings'])
        ->name('api.pos.sales-report-exclusion.settings');

    Route::get('/check/{item}', [\App\Http\Controllers\API\v1\pos\SalesReportExclusionController::class, 'check'])
        ->name('api.pos.sales-report-exclusion.check');
});
```

---

## 1.8 Livewire Components

### 1.8.1 SalesReportExclusionToggle

**File:** `app/Livewire/SuperAdmin/SalesReportExclusionToggle.php`

```php
<?php

namespace App\Livewire\SuperAdmin;

use Livewire\Component;
use App\Models\BusinessSettings;

class SalesReportExclusionToggle extends Component
{
    public $salesReportExclusionEnabled;
    public $salesReportExclusionShowOriginal;

    public function mount()
    {
        $this->salesReportExclusionEnabled = BusinessSettings::get('sales_report_exclusion_enabled', false);
        $this->salesReportExclusionShowOriginal = BusinessSettings::get('sales_report_exclusion_show_original', false);
    }

    public function toggleExclusion()
    {
        $this->salesReportExclusionEnabled = !$this->salesReportExclusionEnabled;
        BusinessSettings::updateOrCreate(
            ['key' => 'sales_report_exclusion_enabled'],
            ['value' => $this->salesReportExclusionEnabled]
        );
        $this->emit('notify', 'success', 'Sales Report Exclusion ' . ($this->salesReportExclusionEnabled ? 'enabled' : 'disabled'));
    }

    public function toggleShowOriginal()
    {
        $this->salesReportExclusionShowOriginal = !$this->salesReportExclusionShowOriginal;
        BusinessSettings::updateOrCreate(
            ['key' => 'sales_report_exclusion_show_original'],
            ['value' => $this->salesReportExclusionShowOriginal]
        );
        $this->emit('notify', 'success', 'Show original ' . ($this->salesReportExclusionShowOriginal ? 'enabled' : 'disabled'));
    }

    public function render()
    {
        return view('livewire.superadmin.sales-report-exclusion-toggle');
    }
}
```

---

### 1.8.2 SalesReportExcludedItemsTable

**File:** `app/Livewire/SuperAdmin/SalesReportExcludedItemsTable.php`

```php
<?php

namespace App\Livewire\SuperAdmin;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Settings\SalesReportExcludedItem;

class SalesReportExcludedItemsTable extends Component
{
    use WithPagination;

    public $search = '';
    public $storeId = null;
    public $perPage = 25;

    protected $listeners = [
        'refreshTable' => 'refresh',
        'itemAdded' => 'refresh',
        'itemUpdated' => 'refresh'
    ];

    public function refresh()
    {
        $this->resetPage();
    }

    public function getItemsProperty()
    {
        return SalesReportExcludedItem::with(['item', 'store', 'creator'])
            ->when($this->search, function($query) {
                $query->whereHas('item', function($q) {
                    $q->where('name', 'like', '%'.$this->search.'%')
                      ->orWhere('barcode', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->storeId, function($query) {
                $query->where('store_id', $this->storeId);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($this->perPage);
    }

    public function toggleItem($itemId)
    {
        $item = SalesReportExcludedItem::findOrFail($itemId);
        $item->update([
            'is_active' => !$item->is_active,
            'updated_by' => auth('admin')->id()
        ]);
        $this->emit('notify', 'success', 'Item ' . ($item->fresh()->is_active ? 'activated' : 'deactivated'));
    }

    public function removeItem($itemId)
    {
        $item = SalesReportExcludedItem::findOrFail($itemId);
        $item->delete();
        $this->emit('notify', 'success', 'Item removed from exclusion list');
    }

    public function render()
    {
        return view('livewire.superadmin.sales-report-excluded-items-table', [
            'items' => $this->items
        ]);
    }
}
```

---

### 1.8.3 SalesReportExcludedItemModal

**File:** `app/Livewire/SuperAdmin/SalesReportExcludedItemModal.php`

```php
<?php

namespace App\Livewire\SuperAdmin;

use Livewire\Component;
use App\Models\Products\Item;
use App\Models\Settings\Store;
use App\Models\Settings\SalesReportExcludedItem;

class SalesReportExcludedItemModal extends Component
{
    public $isOpen = false;
    public $itemId = null;
    public $storeId = null;
    public $reason = '';
    public $search = '';

    public $items = [];
    public $stores = [];

    protected $listeners = ['openExcludedItemModal' => 'open'];

    public function mount()
    {
        $this->stores = Store::orderBy('name')->get();
    }

    public function open($params = [])
    {
        $this->isOpen = true;
        $this->itemId = $params['itemId'] ?? null;
        $this->storeId = $params['storeId'] ?? null;
        $this->reason = $params['reason'] ?? '';

        if (isset($params['itemId'])) {
            $this->items = Item::where('id', $params['itemId'])->get();
        }
    }

    public function updatedSearch()
    {
        if (strlen($this->search) > 2) {
            $this->items = Item::where('name', 'like', '%'.$this->search.'%')
                ->orWhere('barcode', 'like', '%'.$this->search.'%')
                ->limit(10)
                ->get();
        } else {
            $this->items = [];
        }
    }

    public function selectItem($itemId)
    {
        $item = Item::find($itemId);
        if ($item) {
            $this->itemId = $itemId;
            $this->search = $item->name;
            $this->items = [];
        }
    }

    public function save()
    {
        $this->validate([
            'itemId' => 'required|exists:items,id',
            'storeId' => 'required|exists:stores,id',
        ]);

        $data = [
            'item_id' => $this->itemId,
            'store_id' => $this->storeId,
            'reason' => $this->reason,
            'created_by' => auth('admin')->id(),
        ];

        // Check if already exists
        $existing = SalesReportExcludedItem::withTrashed()
            ->where('item_id', $this->itemId)
            ->where('store_id', $this->storeId)
            ->first();

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
            }
            $existing->update($data);
            $message = 'Item updated successfully';
        } else {
            SalesReportExcludedItem::create($data);
            $message = 'Item added successfully';
        }

        $this->emit('itemAdded');
        $this->emit('notify', 'success', $message);
        $this->close();
    }

    public function close()
    {
        $this->isOpen = false;
        $this->reset(['itemId', 'storeId', 'reason', 'search', 'items']);
    }

    public function render()
    {
        return view('livewire.superadmin.sales-report-excluded-item-modal');
    }
}
```

---

## 1.9 Blade Views

### 1.9.1 sales-report-exclusion-toggle.blade.php

**File:** `resources/views/livewire/superadmin/sales-report-exclusion-toggle.blade.php`

```blade
<div class="space-y-6">
    <!-- Main Toggle Card -->
    <div class="p-4 bg-white rounded-lg shadow">
        <div class="flex justify-between items-start">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Sales Report Exclusion System</h3>
                <p class="text-sm text-gray-600 mt-1">
                    Enable to exclude specific items from sales reporting and Sales Report reporting.
                    <span class="text-red-500 font-medium">This modifies tax reporting data.</span>
                </p>
            </div>
        </div>

        <div class="mt-4">
            <label class="flex items-center cursor-pointer">
                <input
                    type="checkbox"
                    wire:model.live="salesReportExclusionEnabled"
                    wire:click="toggleExclusion"
                    class="sr-only peer"
                >
                <div class="relative w-14 h-7 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-blue-600"></div>
                <span class="ms-3 text-sm font-medium text-gray-900">
                    {{ $salesReportExclusionEnabled ? 'Enabled' : 'Disabled' }}
                </span>
            </label>
        </div>

        @if($salesReportExclusionEnabled)
        <div class="mt-4 p-3 bg-blue-50 rounded-lg">
            <label class="flex items-center cursor-pointer">
                <input
                    type="checkbox"
                    wire:model.live="salesReportExclusionShowOriginal"
                    wire:click="toggleShowOriginal"
                    class="sr-only peer"
                >
                <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-0.5 after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-500"></div>
                <span class="ms-3 text-sm font-medium text-gray-900">
                    Show Original Values
                </span>
            </label>
            <p class="text-xs text-gray-500 mt-1">
                When checked, displays unfiltered data. When unchecked, displays Sales Report-adjusted data.
            </p>
        </div>
        @endif
    </div>

    <!-- Excluded Items Section -->
    @if($salesReportExclusionEnabled)
    <div class="p-4 bg-white rounded-lg shadow">
        <div class="flex justify-between items-center">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Excluded Items</h3>
                <p class="text-sm text-gray-600 mt-1">
                    Items in this list will be excluded from sales reporting and Sales Report reports.
                </p>
            </div>
            <button
                wire:click="$emit('openExcludedItemModal', {})"
                class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700 transition-colors"
            >
                + Add Item
            </button>
        </div>

        <!-- Datatable -->
        <livewire:superadmin.sales-report-excluded-items-table />
    </div>
    @endif
</div>
```

---

### 1.9.2 sales-report-excluded-items-table.blade.php

**File:** `resources/views/livewire/superadmin/sales-report-excluded-items-table.blade.php`

```blade
<div class="mt-4 overflow-x-auto">
    <!-- Search and Filter -->
    <div class="flex flex-wrap gap-4 mb-4">
        <div class="flex-1 min-w-[250px]">
            <label for="search" class="sr-only">Search</label>
            <input
                type="text"
                id="search"
                wire:model.live.debounce.500ms="search"
                placeholder="Search by item name or barcode..."
                class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
            >
        </div>
        <div class="min-w-[200px]">
            <select
                wire:model.live="storeId"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
            >
                <option value="">All Stores</option>
                @foreach(\App\Models\Settings\Store::orderBy('name')->get() as $store)
                    <option value="{{ $store->id }}">{{ $store->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="min-w-[100px]">
            <select
                wire:model.live="perPage"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
            >
                <option value="10">10 per page</option>
                <option value="25">25 per page</option>
                <option value="50">50 per page</option>
                <option value="100">100 per page</option>
            </select>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Store</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reason</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($items as $item)
                    <tr class="{{ $item->trashed() ? 'bg-gray-100' : '' }}">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div>
                                <div class="text-sm font-medium text-gray-900">{{ $item->item->name ?? 'N/A' }}</div>
                                <div class="text-xs text-gray-500">{{ $item->item->barcode ?? 'No barcode' }}</div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">{{ $item->store->name ?? 'N/A' }}</div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm text-gray-900">{{ $item->reason ?? '-' }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $item->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ $item->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $item->created_at->format('M d, Y H:i') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                            <div class="flex justify-end space-x-2">
                                @if($item->trashed())
                                    <button
                                        wire:click="restoreItem({{ $item->id }})"
                                        class="text-green-600 hover:text-green-800"
                                        title="Restore"
                                    >
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                        </svg>
                                    </button>
                                @else
                                    <button
                                        wire:click="toggleItem({{ $item->id }})"
                                        class="text-blue-600 hover:text-blue-800"
                                        title="{{ $item->is_active ? 'Deactivate' : 'Activate' }}"
                                    >
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                            @if($item->is_active)
                                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"></path>
                                            @else
                                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                            @endif
                                        </svg>
                                    </button>
                                @endif
                                <button
                                    wire:click="removeItem({{ $item->id }})"
                                    wire:confirm="Are you sure you want to remove this item?"
                                    class="text-red-600 hover:text-red-800"
                                    title="Remove"
                                >
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                            No items found
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    @if($items->hasPages())
        <div class="mt-4 px-6 py-3 bg-white rounded-lg">
            {{ $items->links() }}
        </div>
    @endif
</div>

@script
<script>
    // Open modal for adding new item
    document.addEventListener('livewire:init', () => {
        Livewire.on('openExcludedItemModal', (params) => {
            Livewire.dispatch('open-excluded-item-modal', params);
        });
    });
</script>
@endscript
```

---

### 1.9.3 sales-report-excluded-item-modal.blade.php

**File:** `resources/views/livewire/superadmin/sales-report-excluded-item-modal.blade.php`

```blade
<div>
    <!-- Modal Overlay -->
    <div
        x-data="{ open: @entangle('isOpen') }"
        x-show="open"
        x-on:open-excluded-item-modal.window="open = true; $nextTick(() => { $wire.open($event.detail) })"
        x-on:keydown.escape.window="open = false; $wire.close()"
        x-on:click.away="open = false; $wire.close()"
        class="fixed inset-0 z-50 overflow-y-auto"
        style="display: none;"
    >
        <!-- Overlay -->
        <div
            x-show="open"
            x-transition:enter="transition-opacity duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity duration-300"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-black bg-opacity-50"
        ></div>

        <!-- Modal Panel -->
        <div
            x-show="open"
            x-transition:enter="transition-all duration-300"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition-all duration-300"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="fixed top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-white rounded-lg shadow-xl w-full max-w-2xl"
        >
            <!-- Header -->
            <div class="flex justify-between items-center p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">
                    {{ $itemId ? 'Edit Excluded Item' : 'Add Item to Exclusion List' }}
                </h3>
                <button
                    x-on:click="open = false; $wire.close()"
                    class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100"
                >
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                    </svg>
                </button>
            </div>

            <!-- Body -->
            <div class="p-6">
                <div class="space-y-4">
                    <!-- Item Search -->
                    <div>
                        <label for="search" class="block text-sm font-medium text-gray-700">Item</label>
                        <div class="relative mt-1">
                            <input
                                type="text"
                                id="search"
                                wire:model.live="search"
                                placeholder="Search by name or barcode..."
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                            >
                            @if(!empty($items))
                                <div class="absolute z-10 w-full mt-1 bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden">
                                    <ul class="max-h-60 overflow-y-auto">
                                        @foreach($items as $item)
                                            <li
                                                wire:click="selectItem({{ $item->id }})"
                                                class="px-3 py-2 cursor-pointer hover:bg-gray-100"
                                            >
                                                <div class="text-sm font-medium">{{ $item->name }}</div>
                                                <div class="text-xs text-gray-500">{{ $item->barcode }}</div>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </div>
                        @if($itemId && $item = \App\Models\Products\Item::find($itemId))
                            <div class="mt-2 p-2 bg-gray-50 rounded-lg">
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    <div class="ml-2">
                                        <div class="text-sm font-medium">{{ $item->name }}</div>
                                        <div class="text-xs text-gray-500">{{ $item->barcode }}</div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- Store Selection -->
                    <div>
                        <label for="storeId" class="block text-sm font-medium text-gray-700">Store</label>
                        <select
                            id="storeId"
                            wire:model="storeId"
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        >
                            <option value="">Select a store</option>
                            @foreach($stores as $store)
                                <option value="{{ $store->id }}">{{ $store->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Reason -->
                    <div>
                        <label for="reason" class="block text-sm font-medium text-gray-700">Reason (Optional)</label>
                        <textarea
                            id="reason"
                            wire:model="reason"
                            rows="3"
                            placeholder="Why is this item excluded? (for audit purposes)"
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        ></textarea>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="flex justify-end p-6 border-t border-gray-200">
                <button
                    x-on:click="open = false; $wire.close()"
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
                >
                    Cancel
                </button>
                <button
                    wire:click="save"
                    wire:loading.attr="disabled"
                    class="ml-3 px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                >
                    <span wire:loading.remove>
                        {{ $itemId ? 'Update Item' : 'Add Item' }}
                    </span>
                    <span wire:loading>
                        Processing...
                    </span>
                </button>
            </div>
        </div>
    </div>
</div>
```

---

## 1.10 Middleware

### 1.10.1 SuperadminCheck

**File:** `app/Http/Middleware/SuperadminCheck.php`

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SuperadminCheck
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $admin = $request->user('admin');

        // Check if admin is superadmin
        // Implementation depends on how superadmin is identified
        // Assuming there's a method or flag on the Admin model
        if (!$admin || !$admin->isSuperadmin()) {
            throw new HttpException(403, 'Unauthorized. Superadmin access required.');
        }

        return $next($request);
    }
}
```

**Register in Kernel.php:**

```php
// app/Http/Kernel.php
protected $routeMiddleware = [
    // ... existing middleware
    'superadmin' => \App\Http\Middleware\SuperadminCheck::class,
];
```

---

## 1.11 Modifications to Existing Controllers

### 1.11.1 Reading Controllers

All reading controllers (ZreadingController, XreadingController) should be modified to apply Sales Report Exclusion filtering when generating readings.

**Example modification to ZreadingController:**

```php
// In the generate method
public function generate($posId)
{
    $pos = Pos::findOrFail($posId);
    $salesReportExclusionService = app(SalesReportExclusionService::class);

    // Get sales for this POS since last Z-reading
    $sales = Sale::where('pos_id', $posId)
        ->where('store_id', $pos->store_id)
        ->whereNull('zreading_id')
        ->with(['saleLines.item', 'salesReportExclusionHistoricalState'])
        ->orderBy('created_at')
        ->get();

    // Calculate totals from filtered sales
    $filteredSales = $sales->map(function($sale) use ($salesReportExclusionService) {
        return $salesReportExclusionService->getSaleForSalesReport($sale);
    });

    $totalSales = $filteredSales->sum(function($sale) {
        return $sale->saleLines->sum('total');
    });

    $vatableSales = $filteredSales->sum(function($sale) {
        return $sale->saleLines
            ->where('vat_type', '!=', 'exempt')
            ->sum(function($line) {
                return $line->price * $line->quantity;
            });
    });

    $vat = $filteredSales->sum(function($sale) {
        return $sale->saleLines
            ->where('vat_type', '!=', 'exempt')
            ->sum('vat_amount');
    });

    $vatExempt = $filteredSales->sum(function($sale) {
        return $sale->saleLines
            ->where('vat_type', 'exempt')
            ->sum('total');
    });

    // Create Z-reading
    $zreading = Zreading::create([
        'pos_id' => $posId,
        'store_id' => $pos->store_id,
        'gross_sales' => $totalSales,
        'vatable_sales' => $vatableSales,
        'vat' => $vat,
        'vat_exempt' => $vatExempt,
        // ... other fields
    ]);

    // Attach sales to reading
    Sale::whereIn('id', $sales->pluck('id'))
        ->update(['zreading_id' => $zreading->id]);

    return $zreading;
}
```

---

### 1.11.2 Report Controllers

All Sales Report-related report controllers should apply filtering.

**Example modification:**

```php
// In any report controller method
public function generateReport(Request $request)
{
    $salesReportExclusionService = app(SalesReportExclusionService::class);
    
    $query = Sale::with(['saleLines.item', 'store'])
        ->whereBetween('created_at', [$request->start_date, $request->end_date]);

    if ($request->has('store_id')) {
        $query->where('store_id', $request->store_id);
    }

    $sales = $query->get();

    // Apply Sales Report Exclusion filtering if enabled and not showing original
    if ($salesReportExclusionService->isEnabled() && !$salesReportExclusionService->shouldShowOriginal()) {
        $sales = $sales->map(function($sale) use ($salesReportExclusionService) {
            return $salesReportExclusionService->getSaleForSalesReport($sale);
        });
    }

    // Generate report from $sales
    return $this->generateReportData($sales);
}

// For reports that need a show_original parameter
public function generateReport(Request $request)
{
    $showOriginal = $request->boolean('show_original', false);
    
    // Temporarily override the service's show_original setting
    // Or pass it as a parameter to the filtering methods
    
    $salesReportExclusionService = app(SalesReportExclusionService::class);
    
    $query = Sale::with(['saleLines.item', 'store'])
        ->whereBetween('created_at', [$request->start_date, $request->end_date]);

    $sales = $query->get();

    // Apply filtering
    $sales = $sales->map(function($sale) use ($salesReportExclusionService, $showOriginal) {
        // If show_original is true, bypass filtering
        if ($showOriginal) {
            return $sale;
        }
        return $salesReportExclusionService->getSaleForSalesReport($sale);
    });

    return $this->generateReportData($sales);
}
```

---

### 1.11.3 Receipt/Printing Controllers

**CRITICAL:** Printing controllers must ALWAYS return original data.

```php
// In SaleController or ReceiptController
public function print(Sale $sale)
{
    // ALWAYS load original data - NEVER apply filtering
    $sale->load([
        'saleLines.item.unit',
        'customer',
        'user',
        'store',
        'pos'
    ]);

    // Do NOT apply Sales Report Exclusion filtering
    return response()->json([
        'sale' => $sale->toArray(),
        'is_original' => true, // Flag indicating this is original data
    ]);
}

// For thermal printing
public function thermalPrint(Sale $sale)
{
    // Load original data
    $sale->load([
        'saleLines.item.unit',
        'store',
        'pos',
        'user'
    ]);

    // Generate print data - NEVER filter
    $printData = $this->generateThermalPrintData($sale);

    return response()->json($printData);
}
```

---

## 1.12 Testing

### 1.12.1 Unit Tests

Create tests for:
- `SalesReportExclusionService` methods
- `SalesReportExclusionTrait` query scopes
- Model relationships
- Data filtering logic

### 1.12.2 Integration Tests

Create tests for:
- API endpoints (toggle, items CRUD)
- Sale creation with historical state capture
- Reading generation with filtering
- Report queries with filtering

### 1.12.3 End-to-End Tests

- Superadmin flow: enable exclusion, add items, verify filtering
- Sale flow: create sale, verify display, verify printing
- Reading flow: generate reading, verify values
- Report flow: generate report, verify data

---

## 1.13 Summary

The backend implementation involves:

1. **3 new database tables** (1 new, 2 modified)
2. **4 new models** (2 new, 2 modified)
3. **1 new trait** (SalesReportExclusionTrait)
4. **1 new service** (SalesReportExclusionService)
5. **3 new controllers** (1 Superadmin, 1 POS API, 1 existing modified)
6. **1 new observer** (SaleObserver)
7. **1 new middleware** (SuperadminCheck)
8. **3 new Livewire components** (Toggle, Table, Modal)
9. **3 new Blade views**
10. **Route additions** (Superadmin and POS API)

**Total Files:** ~25-30 files (new + modified)
**Estimated Effort:** 55-60 hours
