<?php

namespace App\Http\Requests\API\v1\mobile\Purchase;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return \Auth::user()->role->prchs_create;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'store_id' => ['required', 'exists:stores,id'],
            'purchased' => ['required', 'date'],
            'expected' => ['required', 'numeric'],
            'invoice_no' => ['nullable', 'string'],
            'items' => ['required', 'numeric'],
            'total' => ['required', 'numeric'],
            'status' => ['required', 'bool'],
            'received' => ['required', 'numeric'],
            'user_id' => ['required', 'exists:users,id'],
            'created_by' => ['required', 'exists:users,id'],
            'lines.*' => ['required'],
            'lines.*.product_id' => ['required', 'numeric'],
            'lines.*.unit_id' => ['nullable', 'string'],
            'lines.*.unit_qty' => ['required', 'numeric'],
            'lines.*.unit_name' => ['required', 'string'],
            'lines.*.qty' => ['required', 'numeric'],
            'lines.*.price' => ['required', 'numeric'],
            'lines.*.sub_total' => ['required', 'numeric'],
        ];
    }
}
