<?php

namespace App\Http\Requests\Supplier;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->user()->role->itms_update;
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
                'min:3',
                'max:255',
                Rule::unique('suppliers', 'name')
                    ->ignore($this->supplier->id)
                    ->where('status', true),
            ],
            'tin' => [
                'nullable',
                'string',
                'min:3',
                'max:255'
            ],
            'contact' => [
                'nullable',
                'string',
                'min:3',
                'max:255'
            ],
            'number' => [
                'nullable',
                'string',
                'min:3',
                'max:255'
            ],
            'email' => [
                'nullable',
                'string',
                'min:3',
                'max:255'
            ],
            'address' => [
                'required',
                'string',
                'min:3',
                'max:255'
            ],
            'city' => [
                'required',
                'string',
                'min:3',
                'max:255'
            ],
            'zip' => [
                'required',
                'string',
                'min:3',
                'max:255'
            ],
            'province' => [
                'required',
                'string',
                'min:3',
                'max:255'
            ],
            'note' => [
                'nullable',
                'string',
                'min:3',
                'max:255'
            ],
            'country' => [
                'nullable',
                'string',
                'min:3',
                'max:255'
            ],
        ];
    }
}
