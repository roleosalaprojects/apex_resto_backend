<?php

namespace App\Models\Employees;

use App\Models\Pos\Zreading;
use App\Models\Settings\Pos;
use App\Models\Settings\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftReading extends Model
{
    protected $fillable = [
        'user_id',
        'pos_id',
        'store_id',
        'z_reading_id',
        // Sales & Transactions
        'cash_sales',
        'e_wallet_sales',
        'credit_sales',
        'credit_payments_cash',
        'credit_payments_ewallet',
        'credit_payments_bank',
        'credit_payments_cheque',
        'gross_sales',
        'net_sales',
        'refunds',
        'transactions',
        // Invoice Range
        'first_or',
        'last_or',
        'refund_first_or',
        'refund_last_or',
        // VAT Breakdown
        'vatable',
        'vat',
        'vat_exempt',
        'zero_rated',
        // Discount Summary
        'reg_discount',
        'sc_discount',
        'pwd_discount',
        'solo_parent_discount',
        'naac_discount',
        'vat_special_discounts',
        // VAT Adjustment
        'sc_vat_adjustment',
        'pwd_vat_adjustment',
        'sp_vat_adjustment',
        'naac_vat_adjustment',
        'vat_on_refunds',
        'vatable_on_refunds',
        'vat_exempt_on_refunds',
        'zero_rated_on_refunds',
        // Transaction Counts
        'sc_transactions',
        'pwd_transactions',
        'sp_transactions',
        'naac_transactions',
        'reg_disc_transactions',
        // Cash & Funds
        'cash_in',
        'cash_out',
        // Denomination
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
        'denomination',
        'discrepancy',
        'total_cash',
        'is_store_closure',
    ];

    protected $casts = [
        'is_store_closure' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pos(): BelongsTo
    {
        return $this->belongsTo(Pos::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function zreading(): BelongsTo
    {
        return $this->belongsTo(Zreading::class, 'z_reading_id');
    }
}
