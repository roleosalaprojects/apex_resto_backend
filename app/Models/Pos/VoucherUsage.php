<?php

namespace App\Models\Pos;

use App\Models\Settings\Pos as PosTerminal;
use App\Models\Settings\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoucherUsage extends Model
{
    protected $fillable = [
        'voucher_id',
        'sale_id',
        'user_id',
        'store_id',
        'pos_id',
        'amount_applied',
    ];

    protected function casts(): array
    {
        return [
            'amount_applied' => 'decimal:2',
        ];
    }

    // Relationships
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function pos(): BelongsTo
    {
        return $this->belongsTo(PosTerminal::class);
    }
}
