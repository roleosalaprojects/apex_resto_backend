<?php

namespace App\Http\Requests\Item;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkCategoryUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->role->itms_update;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['required', 'integer', Rule::exists('items', 'id')->where('status', true)],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('status', true)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'item_ids.required' => 'Please select at least one item.',
            'item_ids.min' => 'Please select at least one item.',
            'item_ids.*.exists' => 'One or more selected items are invalid.',
            'category_id.required' => 'Please select a category.',
            'category_id.exists' => 'The selected category is invalid.',
        ];
    }
}
