<?php

namespace App\Models\Pos;

use App\Models\Products\Discount;
use App\Models\Products\Item;
use App\Models\Products\Unit;
use App\Models\Restaurant\KitchenStation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderLine extends Model
{
    use HasFactory, SoftDeletes;

    public const LINE_QUEUED = 0;

    public const LINE_PREPARING = 1;

    public const LINE_READY = 2;

    public const LINE_SERVED = 3;

    public const LINE_VOIDED = 4;

    protected $fillable = [
        'qty',
        'price',
        'unit_name',
        'item_name',
        'discount',
        'sub_total', // qty * price
        'unit_qty', // number of pc or kg in a unit
        'cost',
        'vat_type', // 0 = Non-VAT, 1 = VATable, 2 = Zero-Rated
        'item_id',
        'unit_id',
        'discount_by',
        'discount_id',
        'tax_id',
        'rate',
        'discount_type',
        'pwd_rate',
        'sc_rate',
        'discountable',
        'type',
        'order_id',
        'sales_id', // Sale that settled this line; NULL = unsettled (supports split bills)
        // Kitchen / KDS
        'notes',
        'round',
        'kitchen_station_id',
        'line_status',
        'fired_at',
        'ready_at',
        'served_at',
        'bumped_by',
        'voided_by',
        'void_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'round' => 'integer',
            'line_status' => 'integer',
            'fired_at' => 'datetime',
            'ready_at' => 'datetime',
            'served_at' => 'datetime',
        ];
    }

    public function kitchenStation(): BelongsTo
    {
        return $this->belongsTo(KitchenStation::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sales_id', 'id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }
}
