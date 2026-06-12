<?php

namespace App\Http\Requests\API\v1\mobile\Bank;

use Illuminate\Foundation\Http\FormRequest;

class WithdrawalRequest extends FormRequest
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
            'payee' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'Please enter the withdrawal amount.',
            'amount.numeric' => 'The withdrawal amount must be a valid number.',
            'amount.min' => 'The withdrawal amount must be at least 0.01.',
            'transaction_date.required' => 'Please select a transaction date.',
            'transaction_date.before_or_equal' => 'Transaction date cannot be in the future.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $bank = $this->route('bank');
            if ($bank && $this->amount > $bank->balance) {
                $validator->errors()->add(
                    'amount',
                    'Insufficient balance. Available: '.number_format($bank->balance, 2)
                );
            }
        });
    }
}
