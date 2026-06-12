<?php

namespace App\Http\Requests\ShiftReading;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Sales & Transactions
            'cash_sales' => ['nullable', 'numeric'],
            'e_wallet_sales' => ['nullable', 'numeric'],
            'credit_sales' => ['nullable', 'numeric'],
            'credit_payments_cash' => ['nullable', 'numeric'],
            'credit_payments_ewallet' => ['nullable', 'numeric'],
            'credit_payments_bank' => ['nullable', 'numeric'],
            'credit_payments_cheque' => ['nullable', 'numeric'],
            'gross_sales' => ['nullable', 'numeric'],
            'net_sales' => ['nullable', 'numeric'],
            'refunds' => ['nullable', 'numeric'],
            'transactions' => ['nullable', 'numeric'],
            // Invoice Range
            'first_or' => ['nullable', 'string'],
            'last_or' => ['nullable', 'string'],
            'refund_first_or' => ['nullable', 'string'],
            'refund_last_or' => ['nullable', 'string'],
            // VAT Breakdown
            'vatable' => ['nullable', 'numeric'],
            'vat' => ['nullable', 'numeric'],
            'vat_exempt' => ['nullable', 'numeric'],
            'zero_rated' => ['nullable', 'numeric'],
            // Discount Summary
            'reg_discount' => ['nullable', 'numeric'],
            'sc_discount' => ['nullable', 'numeric'],
            'pwd_discount' => ['nullable', 'numeric'],
            'solo_parent_discount' => ['nullable', 'numeric'],
            'naac_discount' => ['nullable', 'numeric'],
            'vat_special_discounts' => ['nullable', 'numeric'],
            // VAT Adjustment
            'sc_vat_adjustment' => ['nullable', 'numeric'],
            'pwd_vat_adjustment' => ['nullable', 'numeric'],
            'sp_vat_adjustment' => ['nullable', 'numeric'],
            'naac_vat_adjustment' => ['nullable', 'numeric'],
            'vat_on_refunds' => ['nullable', 'numeric'],
            'vatable_on_refunds' => ['nullable', 'numeric'],
            'vat_exempt_on_refunds' => ['nullable', 'numeric'],
            'zero_rated_on_refunds' => ['nullable', 'numeric'],
            // Transaction Counts
            'sc_transactions' => ['nullable', 'numeric'],
            'pwd_transactions' => ['nullable', 'numeric'],
            'sp_transactions' => ['nullable', 'numeric'],
            'naac_transactions' => ['nullable', 'numeric'],
            'reg_disc_transactions' => ['nullable', 'numeric'],
            // Cash & Funds
            'cash_in' => ['nullable', 'numeric'],
            'cash_out' => ['nullable', 'numeric'],
            // Denomination
            'one_thousand' => ['nullable', 'numeric'],
            'five_hundred' => ['nullable', 'numeric'],
            'two_hundred' => ['nullable', 'numeric'],
            'one_hundred' => ['nullable', 'numeric'],
            'fifty' => ['nullable', 'numeric'],
            'twenty' => ['nullable', 'numeric'],
            'ten' => ['nullable', 'numeric'],
            'five' => ['nullable', 'numeric'],
            'one' => ['nullable', 'numeric'],
            'centavos' => ['nullable', 'numeric'],
            'denomination' => ['nullable', 'numeric'],
            'discrepancy' => ['nullable', 'numeric'],
            'total_cash' => ['nullable', 'numeric'],
            'is_store_closure' => ['nullable', 'boolean'],
        ];
    }
}
