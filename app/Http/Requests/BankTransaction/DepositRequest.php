<?php

namespace App\Http\Requests\BankTransaction;

use Illuminate\Foundation\Http\FormRequest;

class DepositRequest extends FormRequest
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
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:255'],
            'payee' => ['nullable', 'string', 'max:255'],
            'transaction_date' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'Please enter the deposit amount.',
            'amount.min' => 'The deposit amount must be at least 0.01.',
            'transaction_date.required' => 'Please select a transaction date.',
        ];
    }
}
