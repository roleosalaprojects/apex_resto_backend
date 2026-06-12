<?php

namespace App\Http\Requests\API\v1\openclaw\Bank;

use App\Models\Accounting\Bank;
use Illuminate\Contracts\Validation\Validator;
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
            'transfer_to_bank_id' => ['required', 'integer', 'exists:banks,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:500'],
            'transaction_date' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'transfer_to_bank_id.required' => 'The destination bank id is required.',
            'transfer_to_bank_id.exists' => 'The destination bank does not exist.',
            'amount.required' => 'The transfer amount is required.',
            'amount.min' => 'The transfer amount must be at least 0.01.',
            'transaction_date.before_or_equal' => 'The transaction date cannot be in the future.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $bank = $this->route('bank');

            if (! $bank instanceof Bank) {
                return;
            }

            if ((int) $this->input('transfer_to_bank_id') === (int) $bank->id) {
                $validator->errors()->add(
                    'transfer_to_bank_id',
                    'Cannot transfer to the same account.',
                );
            }

            if ((float) $this->input('amount') > (float) $bank->balance) {
                $validator->errors()->add(
                    'amount',
                    'Insufficient balance. Available: '.number_format((float) $bank->balance, 2),
                );
            }
        });
    }
}
