<?php

namespace App\Http\Requests\Admin\EmployeeSchedule;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'schedules' => ['required', 'array', 'size:7'],
            'schedules.*.start_time' => ['nullable', 'date_format:H:i'],
            'schedules.*.is_rest_day' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'schedules.required' => 'Schedule data is required.',
            'schedules.size' => 'Schedule must include all 7 days of the week.',
            'schedules.*.start_time.date_format' => 'Start time must be in HH:MM format.',
        ];
    }
}
