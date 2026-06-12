<?php

namespace App\Http\Requests\Admin\Shift;

use Illuminate\Foundation\Http\FormRequest;

class ClockInRequest extends FormRequest
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
            'pos_id' => ['nullable', 'exists:pos,id'],
            'store_id' => ['nullable', 'exists:stores,id'],
            'starting_cash' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'starting_cash.required' => 'Starting cash amount is required.',
            'starting_cash.numeric' => 'Starting cash must be a valid number.',
            'starting_cash.min' => 'Starting cash cannot be negative.',
        ];
    }
}
