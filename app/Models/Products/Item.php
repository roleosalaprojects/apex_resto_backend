<?php

namespace App\Models\Products;

use App\Models\InventoryManagement\Supplier;
use App\Models\Settings\Tax;
use App\Traits\Auditable;
use App\Traits\SerializesDateToAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    use Auditable, HasFactory, SerializesDateToAppTimezone;

    /**
     * High-churn pricing fields that the POS receiving flow and bulk price
     * tools rewrite frequently. Auditing them would flood audit_logs with
     * low-signal rows. Audit everything else (name, category, supplier,
     * status, alert thresholds, special-discount flags, image).
     *
     * @var array<int, string>
     */
    protected array $excludedAuditFields = [
        'cost',
        'prev_cost',
        'price',
        'prev_price',
        'markup',
    ];

    //
    protected $fillable = [
        'barcode',
        'name',
        'category_id',
        'vatable',
        'tax_id',
        'markup',
        'cost',
        'prev_cost',
        'price',
        'prev_price',
        'status',
        'user_id',
        'pwd',
        'senior',
        'solo_parent',
        'naac',
        'supplier_id',
        'discountable',
        'image',
        'type', // 0: sold per piece (PC), 1 = sold per weight (KG)
        'creditable_to_points',
        'low_stock_threshold',
        'priority',
        'featured',
        'featured_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => 'boolean',
            'featured' => 'boolean',
            'featured_order' => 'integer',
        ];
    }

    /**
     * Scope for the /shop homepage spotlight: featured + active items,
     * ordered by featured_order (nulls last) then name.
     */
    public function scopeFeaturedSpotlight(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query
            ->where('featured', true)
            ->where('status', true)
            ->orderByRaw('featured_order IS NULL, featured_order ASC')
            ->orderBy('name');
    }

    /**
     * Image URL that is actually renderable, or null when the item has no
     * image OR its path points at a file missing from disk (product
     * uploads are gitignored, so dev/staging machines often have stale
     * paths). Views fall back to the branded placeholder on null.
     */
    public function displayImageUrl(): ?string
    {
        if (! $this->image) {
            return null;
        }

        if (str_starts_with($this->image, 'http')) {
            return $this->image;
        }

        $relativePath = ltrim($this->image, '/');

        return file_exists(public_path($relativePath))
            ? asset($relativePath)
            : null;
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(ItemStore::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function itemUnits(): HasMany
    {
        return $this->hasMany(ItemUnit::class);
    }

    public function itemStores(): HasMany
    {
        return $this->hasMany(ItemStore::class);
    }

    public function priceHistories(): HasMany
    {
        return $this->hasMany(PriceHistory::class)->orderBy('created_at', 'desc');
    }

    public function wholesalePriceTiers(): HasMany
    {
        return $this->hasMany(WholesalePriceTier::class)->orderBy('min_qty', 'asc');
    }

    public function hasWholesalePricing(): bool
    {
        return $this->wholesalePriceTiers()->exists();
    }

    public function getWholesaleMoq(): ?int
    {
        return $this->wholesalePriceTiers()->min('min_qty');
    }
}
