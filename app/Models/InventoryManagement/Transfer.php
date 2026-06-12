<?php

namespace App\Models\InventoryManagement;

use App\Models\Settings\Store;
use App\Models\User;
use App\Traits\SerializesDateToAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transfer extends Model
{
    use SerializesDateToAppTimezone;

    //
    protected $fillable = [
        'to',
        'source_store',
        'destination_store',
        'total',
        'received',
        'note',
        'created_by',
        'updated_by',
        'received_by',
        'status',
        'user_id',
        'received_at',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by', 'id');
    }

    /**
     * Get the user that owns the Transfer
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by', 'id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'source_store', 'id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'destination_store', 'id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(TransferLine::class);
    }
}
