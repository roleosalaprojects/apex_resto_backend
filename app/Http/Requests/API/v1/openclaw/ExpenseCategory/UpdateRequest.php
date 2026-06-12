<?php

namespace App\Http\Requests\API\v1\openclaw\ExpenseCategory;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'status' => ['sometimes', 'boolean'],
        ];
    }

    public function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge(['name' => trim((string) $this->input('name'))]);
        }
        if ($this->has('description') && $this->input('description') !== null) {
            $this->merge(['description' => trim((string) $this->input('description'))]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Category name cannot be empty.',
            'name.max' => 'Category name must be 255 characters or fewer.',
            'description.max' => 'Description must be 500 characters or fewer.',
        ];
    }
}
