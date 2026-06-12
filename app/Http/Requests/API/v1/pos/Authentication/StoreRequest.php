<?php

namespace App\Http\Requests\API\v1\pos\Authentication;

use Illuminate\Contracts\Validation\ValidationRule;
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
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'pos_id' => [
                'required',
                'integer',
                'exists:pos,id',
            ],
            'requested_by' => [
                'required',
                'exists:users,id',
                'integer',
            ],
            'auth_type' => [
                'required',
                'string',
            ],
            'consignee_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'pos_id.required' => 'POS ID is required.',
            'pos_id.integer' => 'POS ID incorrect value.',
            'pos_id.exists' => 'POS ID does not exist.',
        ];
    }
}
