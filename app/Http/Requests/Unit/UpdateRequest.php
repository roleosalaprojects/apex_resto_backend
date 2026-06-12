<?php

namespace App\Http\Requests\Unit;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return $this->unit->status;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name' => [
                'required',
                Rule::unique('units')
                    ->ignore($this->unit->id)
                    ->where('status', true)
            ],
        ];
    }

    public function messages()
    {
        return [
            'name.required'=>'Unit name is required',
            'name.unique'=>'Unit name is already taken.'
        ];
    }
}
