<?php

namespace App\Models\Pos;

use App\Models\Settings\Pos as PosTerminal;
use App\Models\Settings\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Xreading extends Model
{
    //    protected $fillable = [
    //        'counter',
    //        'cash',
    //        'vatable',
    //        'refunds',
    //        'vat',
    //        'vat_exempt',
    //        'zero_rated',
    //        'current_sales',
    //        'less_refunds',
    //        'transactions',
    //        'sc_discounts',
    //        'pwd_discounts',
    //        'reg_discounts',
    //        'net_sales',
    //        'generated_by',
    //        'first_or',
    //        'last_or',
    //        'pos_id',
    //        'store_id',
    //        'user_id',
    //        'excess_vatable',
    //        'excess_vat',
    //        'excess_non_vat'
    //    ];

    protected $fillable = [
        'txn_no',
        'reading_at',
        'start_at',
        'end_at',
        'beginning_or',
        'ending_or',
        'opening_fund',
        'cash',
        'e_wallet',
        'cheque',
        'card',
        'gift_cert',
        'bank_transfer',
        'credit',
        'void_amount',
        'void_count',
        'return_amount',
        'cashier_name',
        'refunds',
        'withdrawals',
        'cash_in_drawer',
        'short_over',
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
        'user_id',
        'pos_id',
        'store_id',
    ];

    public function store(): HasOne
    {
        return $this->hasOne(Store::class, 'id', 'store_id');
    }

    public function gen(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }

    public function pos(): BelongsTo
    {
        return $this->belongsTo(PosTerminal::class);
    }
}
