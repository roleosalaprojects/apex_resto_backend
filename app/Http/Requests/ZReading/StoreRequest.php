<?php

namespace App\Http\Requests\ZReading;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'transactions' => ['nullable', 'numeric'],
            'cash' => ['nullable', 'numeric'],
            'e_wallet' => ['nullable', 'numeric'],
            'credit_sales' => ['nullable', 'numeric'],
            'credit_payments_cash' => ['nullable', 'numeric'],
            'credit_payments_ewallet' => ['nullable', 'numeric'],
            'credit_payments_bank' => ['nullable', 'numeric'],
            'credit_payments_cheque' => ['nullable', 'numeric'],
            'cash_in' => ['nullable', 'numeric'],
            'cash_out' => ['nullable', 'numeric'],
            'refund' => ['nullable', 'numeric'],
            'vat_on_refunds' => ['nullable', 'numeric'],
            'vatable_on_refunds' => ['nullable', 'numeric'],
            'vat_exempt_on_refunds' => ['nullable', 'numeric'],
            'zero_rated_on_refunds' => ['nullable', 'numeric'],
            'net_sales' => ['nullable', 'numeric'],
            'vatable' => ['nullable', 'numeric'],
            'vat' => ['nullable', 'numeric'],
            'vat_exempt' => ['nullable', 'numeric'],
            'zero_rated' => ['nullable', 'numeric'],
            'reg_discount' => ['nullable', 'numeric'],
            'sc_discount' => ['nullable', 'numeric'],
            'pwd_discount' => ['nullable', 'numeric'],
            'solo_parent_discount' => ['nullable', 'numeric'],
            'naac_discount' => ['nullable', 'numeric'],
            'vat_special_discounts' => ['nullable', 'numeric'],
            'sc_vat_adjustment' => ['nullable', 'numeric'],
            'pwd_vat_adjustment' => ['nullable', 'numeric'],
            'sp_vat_adjustment' => ['nullable', 'numeric'],
            'naac_vat_adjustment' => ['nullable', 'numeric'],
            'sc_transactions' => ['nullable', 'numeric'],
            'pwd_transactions' => ['nullable', 'numeric'],
            'sp_transactions' => ['nullable', 'numeric'],
            'naac_transactions' => ['nullable', 'numeric'],
            'reg_disc_transactions' => ['nullable', 'numeric'],
            'first_or' => ['nullable', 'string'],
            'last_or' => ['nullable', 'string'],
            'refund_first_or' => ['nullable', 'string'],
            'refund_last_or' => ['nullable', 'string'],
            'begin_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'previous_accumulated_sales' => ['required', 'numeric'],
            'present_accumulated_sales' => ['required', 'numeric'],
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
        ];
    }
}
