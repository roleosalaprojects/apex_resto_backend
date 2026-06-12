<?php

namespace App\Http\Requests\API\v1\mobile\Report;

use Illuminate\Foundation\Http\FormRequest;

class GetSaleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return \Auth::user()->role->sls;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'startDate' => ['required', 'date'],
            'endDate' => ['required', 'date']
        ];
    }
}
