<?php

namespace App\Http\Requests\API\v1\mobile\Sale;

use Illuminate\Foundation\Http\FormRequest;

class RefundRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): Bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): Array
    {
        return [
            'line' => 'required|array',
            'line.*.qty' => 'required|numeric|min:1',
            'line.*.product.id' => 'required|exists:sale_lines,id',
        ];
    }
}
