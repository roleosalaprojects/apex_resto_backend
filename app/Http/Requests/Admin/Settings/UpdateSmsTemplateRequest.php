<?php

namespace App\Http\Requests\Admin\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSmsTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->role?->sttngs;
    }

    public function rules(): array
    {
        return [
            // 480 chars = 3 concatenated GSM-7 segments (153 × 3 + slack).
            // Beyond that the cost-per-message stops being worth it for
            // a transactional notification.
            'body' => ['required', 'string', 'max:480'],
            'enabled' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required' => 'The message body is required.',
            'body.max' => 'The message body must be 480 characters or fewer (3 SMS segments).',
        ];
    }
}
