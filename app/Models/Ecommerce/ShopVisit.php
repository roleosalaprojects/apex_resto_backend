<?php

namespace App\Models\Ecommerce;

use App\Models\CustomerRelations\Customer;
use App\Models\Products\Category;
use App\Models\Products\Item;
use App\Traits\SerializesDateToAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopVisit extends Model
{
    use SerializesDateToAppTimezone;

    protected $fillable = [
        'session_id',
        'visitor_id',
        'customer_id',
        'ip_address',
        'user_agent',
        'page_visited',
        'page_type',
        'referrer',
        'referrer_domain',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'device_type',
        'browser',
        'browser_version',
        'platform',
        'product_id',
        'category_id',
        'action',
        'action_data',
        'time_on_page',
        'entered_at',
        'exited_at',
        'country',
        'city',
    ];

    protected function casts(): array
    {
        return [
            'action_data' => 'array',
            'entered_at' => 'datetime',
            'exited_at' => 'datetime',
        ];
    }

    // Relationships
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'product_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    // Scopes
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year);
    }

    public function scopeUniqueVisitors($query)
    {
        return $query->distinct('visitor_id');
    }

    public function scopeByPageType($query, string $type)
    {
        return $query->where('page_type', $type);
    }

    public function scopeByDevice($query, string $device)
    {
        return $query->where('device_type', $device);
    }
}
