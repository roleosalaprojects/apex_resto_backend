<?php

namespace App\Http\Requests\Admin\Shift;

use Illuminate\Foundation\Http\FormRequest;

class ClockOutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return [
            'ending_cash' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ending_cash.required' => 'Ending cash amount is required.',
            'ending_cash.numeric' => 'Ending cash must be a valid number.',
            'ending_cash.min' => 'Ending cash cannot be negative.',
        ];
    }
}
