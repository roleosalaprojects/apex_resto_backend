<?php

namespace App\Models\Pos;

use App\Models\Settings\Pos as PosTerminal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Mavinoo\Batch\Traits\HasBatch;

class Order extends Model
{
    use HasBatch, HasFactory, SoftDeletes;

    public const STATUS_PENDING = 0;

    public const STATUS_REFERENCED = 1;

    public const STATUS_PREPARING = 2;

    public const STATUS_FOR_PICKUP = 3;

    public const STATUS_COMPLETED = 4;

    public const STATUS_CANCELLED = 5;

    public const TYPE_DINE_IN = 0;

    public const TYPE_TAKE_OUT = 1;

    public const TYPE_DELIVERY = 2;

    protected $fillable = [
        'reference',
        'qty',
        'amount',
        'pos_id', // From which POS this was created
        'user_id', // Created by
        'status', // 0: pending, 1: referenced (POS Only), 2: Preparing, 3: For Pickup, 4: Order Completed, 5: Cancelled
        'accepted_by',
        'accepted_at',
        'assigned_by',
        'assigned_at',
        'prepared_by',
        'prepared_at',
        'completed_by',
        'completed_at',
        'cancelled_by',
        'cancelled_at',
        'picked_up_at',
        'sales_id', // reference Sales Invoice if necessary once order is completed.
        // Restaurant ordering
        'order_type', // 0 dine-in, 1 take-out, 2 delivery
        'table_id',
        'pax',
        'sc_count',
        'pwd_count',
        'waiter_id',
        'guest_name',
        'store_id',
        'delivery_address',
        'delivery_contact',
        'delivery_status',
        'notes',
    ];

    public function table(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Restaurant\RestaurantTable::class, 'table_id');
    }

    public function waiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waiter_id', 'id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sales_id', 'id');
    }

    public function pos(): BelongsTo
    {
        return $this->belongsTo(PosTerminal::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    public function acceptor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by', 'id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by', 'id');
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by', 'id');
    }

    public function finisher()
    {
        return $this->belongsTo(User::class, 'completed_by', 'id');
    }

    public function cancelled(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by', 'id');
    }
}
