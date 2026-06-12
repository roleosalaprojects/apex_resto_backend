<?php

namespace App\Http\Requests\Expense;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
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
            'expense_category_id' => ['nullable', 'exists:expense_categories,id'],
            'store_id' => ['nullable', 'exists:stores,id'],
            'payee' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'receipt_number' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'payee.required' => 'Please enter the payee/vendor name.',
        ];
    }
}
