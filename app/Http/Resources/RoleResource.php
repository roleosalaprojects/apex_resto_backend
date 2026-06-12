<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'permissions' => [
                // POS permissions
                'pos' => $this->pos,
                'delete_items' => $this->delete_items,
                'rfnd' => $this->rfnd,
                'discounts' => $this->discounts,
                'print' => $this->print,
                // Back office & Sales
                'bck_offc' => $this->bck_offc,
                'sls' => $this->sls,
                'sttngs' => $this->sttngs,
                // Items
                'itms' => $this->itms,
                'itms_read' => $this->itms_read,
                'itms_create' => $this->itms_create,
                'itms_update' => $this->itms_update,
                'itms_delete' => $this->itms_delete,
                // Adjustments
                'adjstmnts' => $this->adjstmnts,
                'adjstmnts_read' => $this->adjstmnts_read,
                'adjstmnts_create' => $this->adjstmnts_create,
                'adjstmnts_update' => $this->adjstmnts_update,
                'adjstmnts_delete' => $this->adjstmnts_delete,
                // Transfers
                'trnsfrs' => $this->trnsfrs,
                'trnsfrs_read' => $this->trnsfrs_read,
                'trnsfrs_create' => $this->trnsfrs_create,
                'trnsfrs_update' => $this->trnsfrs_update,
                'trnsfrs_delete' => $this->trnsfrs_delete,
                // Employees
                'emplys' => $this->emplys,
                'emplys_read' => $this->emplys_read,
                'emplys_create' => $this->emplys_create,
                'emplys_update' => $this->emplys_update,
                'emplys_delete' => $this->emplys_delete,
                // Roles
                'rl' => $this->rl,
                'rl_read' => $this->rl_read,
                'rl_create' => $this->rl_create,
                'rl_update' => $this->rl_update,
                'rl_delete' => $this->rl_delete,
                // Customers
                'cstmr' => $this->cstmr,
                'cstmr_read' => $this->cstmr_read,
                'cstmr_create' => $this->cstmr_create,
                'cstmr_update' => $this->cstmr_update,
                'cstmr_delete' => $this->cstmr_delete,
                // Stores
                'str' => $this->str,
                'str_read' => $this->str_read,
                'str_create' => $this->str_create,
                'str_update' => $this->str_update,
                'str_delete' => $this->str_delete,
                // Tax
                'tax' => $this->tax,
                'tax_read' => $this->tax_read,
                'tax_create' => $this->tax_create,
                'tax_update' => $this->tax_update,
                'tax_delete' => $this->tax_delete,
                // Purchases
                'prchs' => $this->prchs,
                'prchs_read' => $this->prchs_read,
                'prchs_create' => $this->prchs_create,
                'prchs_update' => $this->prchs_update,
                'prchs_delete' => $this->prchs_delete,
                'prchs_approve' => $this->prchs_approve,
                // Inventory
                'invntry' => $this->invntry,
                'invntry_read' => $this->invntry_read,
                'invntry_create' => $this->invntry_create,
                'invntry_update' => $this->invntry_update,
                'invntry_delete' => $this->invntry_delete,
                // Suppliers
                'spplrs' => $this->spplrs,
                'spplrs_read' => $this->spplrs_read,
                'spplrs_create' => $this->spplrs_create,
                'spplrs_update' => $this->spplrs_update,
                'spplrs_delete' => $this->spplrs_delete,
                // Attendance
                'attndnc' => $this->attndnc,
                'attndnc_read' => $this->attndnc_read,
                'attndnc_create' => $this->attndnc_create,
                'attndnc_update' => $this->attndnc_update,
                'attndnc_delete' => $this->attndnc_delete,
                'attndnc_schedules' => $this->attndnc_schedules,
                // Banking
                'bnkng' => $this->bnkng,
                'bnkng_read' => $this->bnkng_read,
                'bnkng_create' => $this->bnkng_create,
                'bnkng_update' => $this->bnkng_update,
                'bnkng_delete' => $this->bnkng_delete,
                // Expenses
                'expnss' => $this->expnss,
                'expnss_read' => $this->expnss_read,
                'expnss_create' => $this->expnss_create,
                'expnss_update' => $this->expnss_update,
                'expnss_delete' => $this->expnss_delete,
            ],
            'status' => $this->status,
        ];
    }
}
