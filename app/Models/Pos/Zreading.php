<?php

namespace App\Models\Pos;

use App\Models\Settings\Pos as PosTerminal;
use App\Models\Settings\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Zreading extends Model
{
    protected $fillable = [
        'counter',
        'reset_counter',
        'transactions',
        'cash',
        'e_wallet',
        'credit_sales',
        'credit_payments_cash',
        'credit_payments_ewallet',
        'credit_payments_bank',
        'credit_payments_cheque',
        'cash_in',
        'cash_out',
        'refund',
        'vat_on_refunds',
        'vatable_on_refunds',
        'vat_exempt_on_refunds',
        'zero_rated_on_refunds',
        'net_sales',
        'vatable',
        'vat',
        'vat_exempt',
        'zero_rated',
        'reg_discount',
        'sc_discount',
        'pwd_discount',
        'solo_parent_discount',
        'naac_discount',
        'vat_special_discounts',
        'sc_vat_adjustment',
        'pwd_vat_adjustment',
        'sp_vat_adjustment',
        'naac_vat_adjustment',
        'sc_transactions',
        'pwd_transactions',
        'sp_transactions',
        'naac_transactions',
        'reg_disc_transactions',
        'first_or',
        'last_or',
        'refund_first_or',
        'refund_last_or',
        'begin_date',
        'end_date',
        'previous_accumulated_sales',
        'present_accumulated_sales',
        'pos_id',
        'store_id',
        'user_id',
        'one_thousand',
        'five_hundred',
        'two_hundred',
        'one_hundred',
        'fifty',
        'twenty',
        'ten',
        'five',
        'one',
        'centavos',
        'total_cash',
        'discrepancy',
        'excess_vatable',
        'excess_vat',
        'excess_non_vat',
        // TODO::create an e_wallet column to store e-wallet transactions.
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function gen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function pos(): BelongsTo
    {
        return $this->belongsTo(PosTerminal::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
