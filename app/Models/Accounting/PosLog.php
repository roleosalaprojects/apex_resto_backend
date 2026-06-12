<?php

namespace App\Models\Accounting;

use App\Models\Pos\Sale;
use App\Models\Settings\Pos;
use App\Models\Settings\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosLog extends Model
{
    use HasFactory;

    public $fillable = [
        'cash_in',
        'rendered',
        'cash_out',
        'type',
        'reason',
        'so_id',
        'shift_reading_id',
        'pos_id',
        'store_id',
        'user_id',
    ];

    public function sales(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'so_id', 'id');
    }

    public function pos(): BelongsTo
    {
        return $this->belongsTo(Pos::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
