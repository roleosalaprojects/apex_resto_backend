<?php

namespace App\Http\Requests\API\v1\mobile\Expense;

use App\Models\Accounting\Bank;
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
            'bank_id' => ['required', 'exists:banks,id'],
            'expense_category_id' => ['nullable', 'exists:expense_categories,id'],
            'store_id' => ['nullable', 'exists:stores,id'],
            'payee' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'expense_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:1000'],
            'receipt_number' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $validator->errors()->has('bank_id') && ! $validator->errors()->has('amount')) {
                $bank = Bank::find($this->bank_id);
                if ($bank && $this->amount > $bank->balance) {
                    $validator->errors()->add('amount', 'Insufficient bank balance. Available: '.number_format($bank->balance, 2));
                }
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'bank_id.required' => 'Please select a bank account.',
            'bank_id.exists' => 'The selected bank account is invalid.',
            'payee.required' => 'Payee/Vendor name is required.',
            'amount.required' => 'Amount is required.',
            'amount.min' => 'Amount must be greater than 0.',
            'expense_date.required' => 'Expense date is required.',
        ];
    }
}
