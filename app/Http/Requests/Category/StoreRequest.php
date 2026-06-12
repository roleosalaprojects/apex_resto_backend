<?php

namespace App\Http\Requests\Category;

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
        return auth()->user()->role->itms_create;
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
                Rule::unique('categories')->where('status', true),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'image' => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
            'icon' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'Category name is required',
            'name.unique' => 'Category name exists!',
        ];
    }
}
