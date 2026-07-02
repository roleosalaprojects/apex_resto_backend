<?php

namespace App\Models\Pos;

use App\Models\Accounting\Bank;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One tender applied to a multi-tender Sale (sales.payment_type =
 * Sale::PAYMENT_MULTI). Stores the APPLIED amount — change is given from
 * cash and never recorded here — so a sale's rows always sum to its total.
 */
class SalePayment extends Model
{
    /** @use HasFactory<\Database\Factories\Pos\SalePaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'sales_id',
        'payment_type',
        'amount',
        'reference_number',
        'bank_id',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sales_id', 'id');
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }
}
