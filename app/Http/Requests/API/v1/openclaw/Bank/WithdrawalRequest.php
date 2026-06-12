<?php

namespace App\Http\Requests\API\v1\openclaw\Bank;

use App\Models\Accounting\Bank;
use Illuminate\Contracts\Validation\Validator;
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
            'transaction_date' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'The withdrawal amount is required.',
            'amount.numeric' => 'The withdrawal amount must be numeric.',
            'amount.min' => 'The withdrawal amount must be at least 0.01.',
            'transaction_date.before_or_equal' => 'The transaction date cannot be in the future.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $bank = $this->route('bank');

            if ($bank instanceof Bank && (float) $this->input('amount') > (float) $bank->balance) {
                $validator->errors()->add(
                    'amount',
                    'Insufficient balance. Available: '.number_format((float) $bank->balance, 2),
                );
            }
        });
    }
}
