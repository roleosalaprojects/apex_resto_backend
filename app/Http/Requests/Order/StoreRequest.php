<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            // Order Model
            'reference' => ['nullable'],
            'qty' => ['required', 'numeric'],
            'amount' => ['required', 'numeric'],
            'pos_id' => ['required', 'integer'],
            'user_id' => ['required', 'integer'],
            'status' => ['required', 'integer'],

            // OrderLine Model
            'qty.*' => ['required', 'numeric'],
            'price,*' => ['required', 'numeric'],
            'unit_name.*' => ['nullable', 'string'],
            'item_name.*' => ['required', 'string'],
            'discount.*' => ['required', 'integer'], 
            'sub_total.*' => ['required', 'numeric'],
            'unit_qty.*' => ['required', 'numeric'],
            'cost.*' => ['required', 'numeric'],
            'vat_type.*' => ['required', 'integer'],
            'item_id.*' => ['required', 'integer'],
            'unit_id.*' => ['nullable', 'integer'],
            'discount_by.*' => ['nullable', 'integer'],
            'discount_id.*' => ['nullable', 'integer'],
            'tax_id.*' => ['required', 'integer'],
            'rate.*' => ['required', 'numeric'],
            'discount_type.*' => ['required', 'string'],
            'pwd_rate.*' => ['required', 'integer'],
            'sc_rate.*' => ['required', 'integer'],
            'discountable.*' => ['required', 'boolean'],
            'type' => ['required', 'integer'],
        ];
    }
}
