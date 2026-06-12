<?php

namespace App\Http\Requests\Admin\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class BounceChequeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('record-cashless-payment') ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'bounce_note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
