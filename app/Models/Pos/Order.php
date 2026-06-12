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
    ];

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
