<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateColorPaletteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('superadmin') !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $paletteId = $this->route('palette')?->id;

        return [
            'key' => ['required', 'string', 'alpha_dash', 'max:64', Rule::unique('color_palettes', 'key')->ignore($paletteId)],
            'label' => ['required', 'string', 'max:80'],
            'primary' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'on_primary' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'on_secondary' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'primary.regex' => 'The primary color must be a 6-digit hex value (e.g. #1858fd).',
            'secondary.regex' => 'The secondary color must be a 6-digit hex value.',
            'accent.regex' => 'The accent color must be a 6-digit hex value.',
            'on_primary.regex' => 'The text-on-primary color must be a 6-digit hex value.',
            'on_secondary.regex' => 'The text-on-secondary color must be a 6-digit hex value.',
        ];
    }
}
