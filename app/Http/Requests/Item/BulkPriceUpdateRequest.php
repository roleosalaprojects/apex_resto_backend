<?php

namespace App\Http\Requests\Item;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkPriceUpdateRequest extends FormRequest
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
            'update_type' => ['required', Rule::in(['fixed', 'percentage'])],
            'field' => ['required', Rule::in(['price', 'cost', 'markup'])],
            'value' => ['required', 'numeric', 'min:0'],
            'direction' => ['required', Rule::in(['increase', 'decrease'])],
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
            'update_type.required' => 'Please select an update type.',
            'update_type.in' => 'Update type must be either fixed or percentage.',
            'field.required' => 'Please select a field to update.',
            'field.in' => 'Field must be price, cost, or markup.',
            'value.required' => 'Please enter a value.',
            'value.numeric' => 'Value must be a number.',
            'value.min' => 'Value must be at least 0.',
            'direction.required' => 'Please select a direction.',
            'direction.in' => 'Direction must be either increase or decrease.',
        ];
    }
}
