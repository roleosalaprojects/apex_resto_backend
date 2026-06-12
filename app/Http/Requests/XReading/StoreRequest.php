<?php

namespace App\Http\Requests\XReading;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reading_at' => ['required', 'date'],
            'start_at' => ['required', 'date_format:Y-m-d H:i:s'],
            'end_at' => ['required', 'date_format:Y-m-d H:i:s'],
            'beginning_or' => ['required', 'string'],
            'ending_or' => ['required', 'string'],
            'opening_fund' => ['required', 'numeric'],
            'cash' => ['required', 'numeric'],
            'e_wallet' => ['required', 'numeric'],
            'refunds' => ['required', 'numeric'],
            'withdrawals' => ['required', 'numeric'],
            'cash_in_drawer' => ['required', 'numeric'],
            'one_thousand' => ['nullable', 'numeric'],
            'five_hundred' => ['nullable', 'numeric'],
            'two_hundred' => ['nullable', 'numeric'],
            'one_hundred' => ['nullable', 'numeric'],
            'fifty' => ['nullable', 'numeric'],
            'twenty' => ['nullable', 'numeric'],
            'ten' => ['nullable', 'numeric'],
            'five' => ['nullable', 'numeric'],
            'one' => ['nullable', 'numeric'],
            'centavos' => ['nullable', 'numeric'],
            'short_over' => ['nullable', 'numeric'],
            'user_id' => ['required'],
            'pos_id' => ['required'],
            'store_id' => ['required'],
        ];
    }

    public function messages(): array
    {
        return [
            ''
        ];
    }
}
