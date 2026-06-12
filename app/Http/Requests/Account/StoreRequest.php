<?php

namespace App\Http\Requests\Account;

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
            'number' => [
                'nullable',
                'string',
                Rule::unique('accounts', 'number')->where('type', $this->type)
            ],
            'name' => ['required', 'string'],
            'description' => ['required', 'string'],
            'starting_balance' => ['required', 'numeric'],
            'current_balance' => ['required', 'numeric'],
            'type' => ['required', 'integer', 'min:1', 'max:5'],
        ];
    }

    public function messages()
    {
        return [
            'number.unique' => 'This account number has been taken. Please try another account number.',
            'name.required' => 'Account name is required',
            'description.required' => 'Account description is required',
            'starting_balance.required' => 'Account Starting Balance is required.',
            'current_balance.required' => 'Account Current balance is required',
            'type.required' => 'Account type is required.'
        ];
    }
}
