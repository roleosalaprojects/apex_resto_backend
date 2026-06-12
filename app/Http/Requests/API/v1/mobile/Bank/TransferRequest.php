<?php

namespace App\Http\Requests\API\v1\mobile\Bank;

use Illuminate\Foundation\Http\FormRequest;

class TransferRequest extends FormRequest
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
            'transfer_to_bank_id' => ['required', 'exists:banks,id', 'different:bank_id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
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
            'transfer_to_bank_id.required' => 'Please select a destination account.',
            'transfer_to_bank_id.exists' => 'The selected destination account does not exist.',
            'transfer_to_bank_id.different' => 'Cannot transfer to the same account.',
            'amount.required' => 'Please enter the transfer amount.',
            'amount.numeric' => 'The transfer amount must be a valid number.',
            'amount.min' => 'The transfer amount must be at least 0.01.',
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

            // Ensure not transferring to the same bank
            if ($bank && $this->transfer_to_bank_id == $bank->id) {
                $validator->errors()->add(
                    'transfer_to_bank_id',
                    'Cannot transfer to the same account.'
                );
            }
        });
    }
}
