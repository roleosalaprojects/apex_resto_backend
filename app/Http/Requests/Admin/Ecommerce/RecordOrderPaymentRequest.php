<?php

namespace App\Http\Requests\Admin\Ecommerce;

use App\Models\Pos\Sale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Admin "Record Payment" against an EcommerceOrder.
 *
 * Credit (payment_type = 3) is intentionally excluded — the admin path is
 * for *received* cashless payments. If a customer wants the order on
 * credit, that's a POS flow with a credit_limit check.
 */
class RecordOrderPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('record-cashless-payment') ?? false;
    }

    /**
     * @return array<string, array<int, string|\Illuminate\Contracts\Validation\ValidationRule>>
     */
    public function rules(): array
    {
        $bankRequired = [
            Sale::PAYMENT_EWALLET,
            Sale::PAYMENT_BANK_TRANSFER,
            Sale::PAYMENT_CHEQUE,
        ];
        $requiredIfBank = 'required_if:payment_type,'.implode(',', $bankRequired);

        return [
            'payment_type' => ['required', 'integer', Rule::in([
                Sale::PAYMENT_CASH,
                Sale::PAYMENT_EWALLET,
                Sale::PAYMENT_BANK_TRANSFER,
                Sale::PAYMENT_CHEQUE,
            ])],
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'bank_id' => ['nullable', 'integer', 'exists:banks,id', $requiredIfBank],
            'reference_number' => ['nullable', 'string', 'max:120', $requiredIfBank],
            'bank_amount' => ['nullable', 'numeric', 'min:0', $requiredIfBank],
            'note' => ['nullable', 'string', 'max:500'],
            // Optional proof-of-payment photos. 0-5 images, each up to 5MB.
            // Common shapes: GCash screenshot, deposit slip, cheque photo.
            'proofs' => ['nullable', 'array', 'max:5'],
            'proofs.*' => ['file', 'image', 'max:5120', 'mimes:jpg,jpeg,png,webp,heic'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'payment_type.in' => 'Choose Cash, GCash, Bank Transfer, or Cheque.',
            'bank_id.required_if' => 'Bank is required for this payment method.',
            'reference_number.required_if' => 'Reference number is required for this payment method.',
            'bank_amount.required_if' => 'Amount received is required for this payment method.',
            'store_id.required' => 'Choose which store is fulfilling this order.',
            'proofs.max' => 'You can attach at most 5 proof photos per payment.',
            'proofs.*.image' => 'Each proof must be an image file.',
            'proofs.*.mimes' => 'Proof photos must be JPG, PNG, WEBP, or HEIC.',
            'proofs.*.max' => 'Each proof photo must be smaller than 5 MB.',
        ];
    }
}
