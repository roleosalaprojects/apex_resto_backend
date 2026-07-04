<?php

namespace App\Http\Requests\Category;

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
        return auth()->user()->role->itms_update;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        // dd($this->category);
        return [
            'name' => [
                'required',
                Rule::unique('categories')
                    ->ignore($this->category->id)
                    ->where('status', true),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'image' => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
            'old_image' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:100'],
            'kitchen_station_id' => ['nullable', 'integer', 'exists:kitchen_stations,id'],
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
