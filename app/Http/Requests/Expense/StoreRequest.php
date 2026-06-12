<?php

namespace App\Http\Requests\Expense;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'expense_category_id' => ['nullable', 'exists:expense_categories,id'],
            'store_id' => ['nullable', 'exists:stores,id'],
            'bank_id' => ['required', 'exists:banks,id'],
            'payee' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'expense_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:1000'],
            'receipt_number' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bank_id.required' => 'Please select a bank account.',
            'bank_id.exists' => 'The selected bank account does not exist.',
            'payee.required' => 'Please enter the payee/vendor name.',
            'amount.required' => 'Please enter the expense amount.',
            'amount.min' => 'The expense amount must be at least 0.01.',
            'expense_date.required' => 'Please select the expense date.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $bank = \App\Models\Accounting\Bank::find($this->bank_id);
            if ($bank && $this->amount > $bank->balance) {
                $validator->errors()->add(
                    'amount',
                    'Insufficient bank balance. Available: '.number_format($bank->balance, 2)
                );
            }
        });
    }
}
