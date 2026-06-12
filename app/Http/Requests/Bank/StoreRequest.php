<?php

namespace App\Http\Requests\Bank;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'bank_name' => ['required', 'string'],
            'account_name' => ['required', 'string'],
            'account_number' => [
                'required',
                'string',
                Rule::unique('banks', 'account_number')
                    ->where('deleted_at', null)
            ],
            'account_type' => ['required', 'integer'],
            'opening_balance' => ['required', 'numeric'],
            'balance' => ['required', 'numeric'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(){
        return [
            'bank_name.required' => 'Bank name is required.',
            'account_name.required' => 'Account name is required.',
            'account_number.required' => 'Account number is required.',
            'account_type.required' => 'Account type is required.',
            'account_name.string' => 'Account name must be a string.',
            'account_number.string' => 'Account number must be a string.',
            'account_number.unique' => 'Account number already exists.',
            'account_type.integer' => 'Account type must be an integer.',
            'opening_balance.required' => 'Starting balance is required.',
            'description.string' => 'Description must be a string.',
            'opening_balance.numeric' => 'Starting balance must be a number.',
            'description.max' => 'Description must not be greater than 255 characters.',
        ];
    }
}
