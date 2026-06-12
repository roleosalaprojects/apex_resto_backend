<?php

namespace App\Http\Requests\Tax;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return $this->tax->status;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name'=>['required', 'unique:taxes,name,'.$this->tax->id],
            'rate'=>['required', 'integer'],
        ];
    }

    public function messages()
    {
        return [
            'name.required'=>'Tax name is required.',
            'name.unique'=>'Tax name exists.',
            'rate.required'=>'Tax rate is required',
            'rate.integer'=>'Tax rate value is invalid',
        ];
    }
}
