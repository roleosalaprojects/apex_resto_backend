<?php

namespace App\Http\Requests\BankTransaction;

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
        $bankId = $this->route('bank')?->id;

        return [
            'transfer_to_bank_id' => ['required', 'exists:banks,id', 'different:bank_id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:255'],
            'transaction_date' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'transfer_to_bank_id.required' => 'Please select a destination account.',
            'transfer_to_bank_id.exists' => 'The selected destination account does not exist.',
            'transfer_to_bank_id.different' => 'Cannot transfer to the same account.',
            'amount.required' => 'Please enter the transfer amount.',
            'amount.min' => 'The transfer amount must be at least 0.01.',
            'transaction_date.required' => 'Please select a transaction date.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $bank = $this->route('bank');
            if ($bank && $this->amount > $bank->balance) {
                $validator->errors()->add('amount', 'Insufficient balance. Available: '.number_format($bank->balance, 2));
            }
        });
    }
}
