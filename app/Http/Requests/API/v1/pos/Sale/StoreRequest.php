<?php

namespace App\Http\Requests\API\v1\pos\Sale;

use App\Models\CustomerRelations\Customer;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'pos_id' => ['required', 'integer', 'exists:pos,id'],
            'type' => ['nullable', 'boolean'],
            'sale_id' => ['nullable', 'integer', 'exists:sales,id'],

            // Sale details
            'details' => ['required', 'array'],
            'details.payment_type' => ['required', 'integer'],
            'details.reference_number' => ['nullable', 'string'],
            'details.bank_amount' => ['nullable', 'numeric'],
            'details.bank_id' => ['nullable', 'integer', 'exists:banks,id'],
            'details.total' => ['required', 'numeric', 'min:0'],
            'details.cash' => ['required', 'numeric', 'min:0'],
            'details.change' => ['required', 'numeric', 'min:0'],
            'details.profit' => ['nullable', 'numeric'],
            'details.vatable' => ['nullable', 'numeric'],
            'details.vat' => ['nullable', 'numeric'],
            'details.vat_exempt' => ['nullable', 'numeric'],
            'details.zero_rated' => ['nullable', 'numeric'],
            'details.customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'details.points' => ['nullable', 'numeric'],

            // Special discounts
            'details.sc_discount' => ['nullable', 'numeric'],
            'details.pwd_discount' => ['nullable', 'numeric'],
            'details.sp_discount' => ['nullable', 'numeric'],
            'details.naac_discount' => ['nullable', 'numeric'],
            'details.vat_special_discounts' => ['nullable', 'numeric'],

            // Points
            'details.points_used' => ['nullable', 'numeric'],

            // Special discount details
            'details.special_discount_type' => ['nullable', 'integer'],
            'details.special_discount_name' => ['nullable', 'string'],
            'details.special_discount_id' => ['nullable', 'string'],
            'details.special_discount_tin' => ['nullable', 'string'],
            'details.special_discount_child_name' => ['nullable', 'string'],
            'details.special_discount_child_birthdate' => ['nullable', 'string'],
            'details.special_discount_child_age' => ['nullable', 'integer'],

            // Voucher
            'details.voucher_id' => ['nullable', 'integer'],
            'details.voucher_code' => ['nullable', 'string'],
            'details.voucher_discount' => ['nullable', 'numeric'],

            // Ecommerce
            'ecommerce_order_id' => ['nullable', 'integer'],

            // Line items
            'line' => ['required', 'array', 'min:1'],
            'line.*.qty' => ['required', 'numeric', 'min:0.001'],
            'line.*.price' => ['required', 'numeric', 'min:0'],
            'line.*.discount' => ['nullable', 'numeric', 'min:0'],
            'line.*.unit' => ['nullable'],
            'line.*.unit_id' => ['nullable', 'integer'],
            'line.*.unit_qty' => ['nullable', 'numeric'],
            'line.*.vatable' => ['nullable', 'numeric'],
            'line.*.vat' => ['nullable', 'numeric'],
            'line.*.vat_exempt' => ['nullable', 'numeric'],
            'line.*.zero_rated' => ['nullable', 'numeric'],
            'line.*.vat_special_discounts' => ['nullable', 'numeric'],
            'line.*.profit' => ['nullable', 'numeric'],
            'line.*.sc_discount' => ['nullable', 'numeric'],
            'line.*.pwd_discount' => ['nullable', 'numeric'],
            'line.*.sp_discount' => ['nullable', 'numeric'],
            'line.*.naac_discount' => ['nullable', 'numeric'],
            'line.*.product' => ['required', 'array'],
            'line.*.product.id' => ['required', 'integer', 'exists:items,id'],
            'line.*.product.cost' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $paymentType = $this->input('details.payment_type');
            if ($paymentType == 3) {
                $customerId = $this->input('details.customer_id');
                if (! $customerId) {
                    $validator->errors()->add('details.customer_id', 'Customer is required for credit sales.');

                    return;
                }

                $customer = Customer::find($customerId);
                if (! $customer || $customer->credit_limit <= 0) {
                    $validator->errors()->add('details.customer_id', 'Customer does not have a credit line.');

                    return;
                }

                $total = $this->input('details.total', 0);
                if ($total > $customer->available_credit) {
                    $validator->errors()->add('details.total', 'Sale total exceeds available credit. Available: '.$customer->available_credit);
                }
            }
        });
    }

    /**
     * Get custom messages for validation errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pos_id.required' => 'POS terminal is required',
            'pos_id.exists' => 'Invalid POS terminal',
            'details.required' => 'Sale details are required',
            'details.total.required' => 'Sale total is required',
            'details.cash.required' => 'Cash amount is required',
            'line.required' => 'At least one item is required',
            'line.min' => 'At least one item is required',
            'line.*.qty.required' => 'Item quantity is required',
            'line.*.qty.min' => 'Item quantity must be greater than 0',
            'line.*.price.required' => 'Item price is required',
            'line.*.product.required' => 'Product information is required',
            'line.*.product.id.required' => 'Product ID is required',
            'line.*.product.id.exists' => 'Invalid product',
        ];
    }
}
