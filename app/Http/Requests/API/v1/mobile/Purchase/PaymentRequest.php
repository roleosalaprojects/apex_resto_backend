<?php

namespace App\Http\Requests\API\v1\mobile\Purchase;

use App\Models\Accounting\Bank;
use App\Models\InventoryManagement\Purchase;
use App\Models\InventoryManagement\PurchasePayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentRequest extends FormRequest
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
            'bank_id' => ['required', 'exists:banks,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'payment_method' => [
                'required',
                Rule::in([
                    PurchasePayment::METHOD_CASH,
                    PurchasePayment::METHOD_CHECK,
                    PurchasePayment::METHOD_BANK_TRANSFER,
                    PurchasePayment::METHOD_EWALLET,
                ]),
            ],
            'check_number' => ['required_if:payment_method,'.PurchasePayment::METHOD_CHECK, 'nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:500'],
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
            'amount.required' => 'Please enter the payment amount.',
            'amount.numeric' => 'The payment amount must be a valid number.',
            'amount.min' => 'The payment amount must be at least 0.01.',
            'payment_date.required' => 'Please select a payment date.',
            'payment_date.before_or_equal' => 'Payment date cannot be in the future.',
            'payment_method.required' => 'Please select a payment method.',
            'payment_method.in' => 'Invalid payment method selected.',
            'check_number.required_if' => 'Check number is required for check payments.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var Purchase $purchase */
            $purchase = $this->route('purchase');
            $amount = $this->input('amount', 0);
            $bankId = $this->input('bank_id');

            // Check if PO is approved
            if (! $purchase->isApproved()) {
                $validator->errors()->add(
                    'purchase',
                    'This purchase order is not approved. Only approved POs can receive payments.'
                );

                return;
            }

            // Check if PO is already fully paid
            if ($purchase->isFullyPaid()) {
                $validator->errors()->add(
                    'purchase',
                    'This purchase order is already fully paid.'
                );

                return;
            }

            // Check remaining balance
            $remainingBalance = $purchase->remaining_balance;
            if ($amount > $remainingBalance) {
                $validator->errors()->add(
                    'amount',
                    'Payment amount exceeds remaining balance. Remaining: '.number_format($remainingBalance, 2)
                );
            }

            // Check bank balance
            if ($bankId) {
                $bank = Bank::find($bankId);
                if ($bank && $amount > $bank->balance) {
                    $validator->errors()->add(
                        'bank_id',
                        'Insufficient bank balance. Available: '.number_format($bank->balance, 2)
                    );
                }
            }
        });
    }
}
