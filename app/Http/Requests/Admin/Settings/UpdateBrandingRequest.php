<?php

namespace App\Http\Requests\Admin\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBrandingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'palette_key' => [
                'required',
                'string',
                Rule::exists('color_palettes', 'key')->where('is_active', true),
            ],
            'brand_name' => [
                'nullable',
                'string',
                'max:60',
                "regex:/^[\\p{L}\\p{N}\\s&\\-.']+$/u",
            ],
            'logo' => [
                'nullable',
                'image',
                'mimes:png,jpg,jpeg,webp',
                'mimetypes:image/png,image/jpeg,image/webp',
                'max:500',
                'dimensions:max_width=1200,max_height=400',
            ],
            'remove_logo' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'palette_key.exists' => 'That palette is not available.',
            'brand_name.regex' => 'Brand name can only contain letters, numbers, spaces, and the characters & - . \'.',
            'logo.mimes' => 'Logo must be a PNG, JPG, or WEBP file. SVG is not allowed.',
            'logo.mimetypes' => 'Logo must be a PNG, JPG, or WEBP file. SVG is not allowed.',
            'logo.max' => 'Logo must be 500KB or smaller.',
            'logo.dimensions' => 'Logo must fit within 1200×400 pixels.',
        ];
    }
}
