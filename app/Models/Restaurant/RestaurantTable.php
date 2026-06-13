<?php

namespace App\Models\Restaurant;

use App\Models\Pos\Order;
use App\Models\Settings\Store;
use App\Traits\Auditable;
use App\Traits\SerializesDateToAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RestaurantTable extends Model
{
    use Auditable, HasFactory, SerializesDateToAppTimezone, SoftDeletes;

    public const STATUS_AVAILABLE = 0;

    public const STATUS_OCCUPIED = 1;

    public const STATUS_RESERVED = 2;

    public const STATUS_INACTIVE = 3;

    protected $fillable = [
        'name',
        'number',
        'area',
        'seats',
        'status',
        'store_id',
        'user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seats' => 'integer',
            'status' => 'integer',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'table_id');
    }

    /**
     * The currently open dine-in order on this table, if any.
     */
    public function openOrder(): HasMany
    {
        return $this->orders()
            ->whereNull('sales_id')
            ->whereNotIn('status', [Order::STATUS_CANCELLED, Order::STATUS_COMPLETED]);
    }
}
