<?php

namespace App\Http\Requests\Admin\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class ClearChequeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('record-cashless-payment') ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'cleared_date' => ['required', 'date', 'before_or_equal:today'],
            'clearing_reference' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cleared_date.required' => 'Pick the date the cheque cleared.',
            'cleared_date.before_or_equal' => 'Clearing date cannot be in the future.',
        ];
    }
}
