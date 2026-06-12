<?php

namespace App\Models\InventoryManagement;

use App\Models\Settings\Store;
use App\Models\User;
use App\Traits\SerializesDateToAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Adjustment extends Model
{
    use SerializesDateToAppTimezone;

    // status: 2 - Pending, 1 - Approved, 0 - Deleted.

    protected $fillable = [
        'so',
        'total',
        'store_id',
        'received',
        'note',
        'created_by',
        'updated_by',
        'received_by',
        'status',
        'user_id',
        'reason',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by', 'id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(AdjustmentLine::class);
    }
}
