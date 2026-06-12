<?php

namespace App\Http\Requests\SpecialCustomer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->user()->role->cstmr_create;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('special_customers', 'name')
                ->where('deleted_at', null)
            ],
            'identifier' => [
                'required',
                'string',
                'max:255',
                Rule::unique('special_customers', 'identifier')
                    ->where('deleted_at', null)
            ],
            'tin' => [
                'required',
                'string',
                'max:255',
                Rule::unique('special_customers', 'tin')
                    ->where('deleted_at', null)
            ],
            'type' => [
                'required',
                'integer'
            ],
            'child_name' => [
                'required_if:type,==,2',
                Rule::unique('special_customers', 'child_name')
                    ->where('child_name', '<>' ,null)
                    ->where('deleted_at', null)
            ],
            'child_age' => [
                'required_if:type,==,2',
            ]
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Customer name is required.',
            'name.string' => 'Customer name must be a string.',
            'name.max' => 'Customer name cannot be longer than 255 characters.',
            'identifier.required' => 'Identifier field is required.',
            'identifier.string' => 'Identifier must be a string.',
            'identifier.max' => 'Identifier cannot be longer than 255 characters.',
            'identifier.unique' => 'Identifier must be unique.',
            'tin.required' => 'Tax Identification Number is required.',
            'tin.string' => 'Tax Identification Number must be a string.',
            'tin.max' => 'Tax Identification Number cannot be longer than 255 characters.',
            'tin.unique' => 'Tax Identification Number must be unique.',
            'type.required' => 'Type is required.',
            'type.integer' => 'Type must be an integer.',
            'child_name.required' => 'Child name is required.',
            'child_name.string' => 'Child name must be a string.',
            'child_name.unique' => 'Child name must be unique.',
            'child_age.required' => 'Child age is required.',
            'child_age.integer' => 'Child age must be a integer.',
        ];
    }
}
