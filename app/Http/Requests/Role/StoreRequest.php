<?php

namespace App\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->user()?->role?->rl_create ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:255',
                Rule::unique('roles', 'name')->where('status', true),
            ],
            // POS permissions
            'pos' => ['nullable', 'boolean'],
            'delete_items' => ['nullable', 'boolean'],
            'rfnd' => ['nullable', 'boolean'],
            'discounts' => ['nullable', 'boolean'],
            'print' => ['nullable', 'boolean'],
            // Back office & Sales
            'bck_offc' => ['nullable', 'boolean'],
            'sls' => ['nullable', 'boolean'],
            // Items
            'itms' => ['nullable', 'boolean'],
            'itms_read' => ['nullable', 'boolean'],
            'itms_create' => ['nullable', 'boolean'],
            'itms_update' => ['nullable', 'boolean'],
            'itms_delete' => ['nullable', 'boolean'],
            // Adjustments
            'adjstmnts' => ['nullable', 'boolean'],
            'adjstmnts_read' => ['nullable', 'boolean'],
            'adjstmnts_create' => ['nullable', 'boolean'],
            'adjstmnts_update' => ['nullable', 'boolean'],
            'adjstmnts_delete' => ['nullable', 'boolean'],
            // Transfers
            'trnsfrs' => ['nullable', 'boolean'],
            'trnsfrs_read' => ['nullable', 'boolean'],
            'trnsfrs_create' => ['nullable', 'boolean'],
            'trnsfrs_update' => ['nullable', 'boolean'],
            'trnsfrs_delete' => ['nullable', 'boolean'],
            // Employees
            'emplys' => ['nullable', 'boolean'],
            'emplys_read' => ['nullable', 'boolean'],
            'emplys_create' => ['nullable', 'boolean'],
            'emplys_update' => ['nullable', 'boolean'],
            'emplys_delete' => ['nullable', 'boolean'],
            // Roles
            'rl' => ['nullable', 'boolean'],
            'rl_read' => ['nullable', 'boolean'],
            'rl_create' => ['nullable', 'boolean'],
            'rl_update' => ['nullable', 'boolean'],
            'rl_delete' => ['nullable', 'boolean'],
            // Customers
            'cstmr' => ['nullable', 'boolean'],
            'cstmr_read' => ['nullable', 'boolean'],
            'cstmr_create' => ['nullable', 'boolean'],
            'cstmr_update' => ['nullable', 'boolean'],
            'cstmr_delete' => ['nullable', 'boolean'],
            // Stores
            'str' => ['nullable', 'boolean'],
            'str_read' => ['nullable', 'boolean'],
            'str_create' => ['nullable', 'boolean'],
            'str_update' => ['nullable', 'boolean'],
            'str_delete' => ['nullable', 'boolean'],
            // Tax
            'tax' => ['nullable', 'boolean'],
            'tax_read' => ['nullable', 'boolean'],
            'tax_create' => ['nullable', 'boolean'],
            'tax_update' => ['nullable', 'boolean'],
            'tax_delete' => ['nullable', 'boolean'],
            // Settings
            'sttngs' => ['nullable', 'boolean'],
            // Purchases
            'prchs' => ['nullable', 'boolean'],
            'prchs_read' => ['nullable', 'boolean'],
            'prchs_create' => ['nullable', 'boolean'],
            'prchs_update' => ['nullable', 'boolean'],
            'prchs_delete' => ['nullable', 'boolean'],
            'prchs_approve' => ['nullable', 'boolean'],
            // Inventory
            'invntry' => ['nullable', 'boolean'],
            'invntry_read' => ['nullable', 'boolean'],
            'invntry_create' => ['nullable', 'boolean'],
            'invntry_update' => ['nullable', 'boolean'],
            'invntry_delete' => ['nullable', 'boolean'],
            // Suppliers
            'spplrs' => ['nullable', 'boolean'],
            'spplrs_read' => ['nullable', 'boolean'],
            'spplrs_create' => ['nullable', 'boolean'],
            'spplrs_update' => ['nullable', 'boolean'],
            'spplrs_delete' => ['nullable', 'boolean'],
        ];
    }
}
