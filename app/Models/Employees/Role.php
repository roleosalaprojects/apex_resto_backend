<?php

namespace App\Models\Employees;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory;

    //
    protected $fillable = [
        'name',
        'pos',
        'delete_items',
        'rfnd',
        'discounts',
        'print',
        'bck_offc',
        'sls',
        'itms',
        'itms_read',
        'itms_create',
        'itms_update',
        'itms_delete',
        'adjstmnts',
        'adjstmnts_read',
        'adjstmnts_create',
        'adjstmnts_update',
        'adjstmnts_delete',
        'trnsfrs',
        'trnsfrs_read',
        'trnsfrs_create',
        'trnsfrs_update',
        'trnsfrs_delete',
        'emplys',
        'emplys_read',
        'emplys_create',
        'emplys_update',
        'emplys_delete',
        'rl',
        'rl_read',
        'rl_create',
        'rl_update',
        'rl_delete',
        'cstmr',
        'cstmr_read',
        'cstmr_create',
        'cstmr_update',
        'cstmr_delete',
        'str',
        'str_read',
        'str_create',
        'str_update',
        'str_delete',
        'tax',
        'tax_read',
        'tax_create',
        'tax_update',
        'tax_delete',
        'sttngs',
        'status',
        'user_id',
        'prchs',
        'prchs_read',
        'prchs_create',
        'prchs_update',
        'prchs_delete',
        'prchs_approve',
        'invntry',
        'invntry_read',
        'invntry_create',
        'invntry_update',
        'invntry_delete',
        'spplrs',
        'spplrs_read',
        'spplrs_create',
        'spplrs_update',
        'spplrs_delete',
        'attndnc',
        'attndnc_read',
        'attndnc_create',
        'attndnc_update',
        'attndnc_delete',
        'attndnc_schedules',
        'bnkng',
        'bnkng_read',
        'bnkng_create',
        'bnkng_update',
        'bnkng_delete',
        'expnss',
        'expnss_read',
        'expnss_create',
        'expnss_update',
        'expnss_delete',
        'pulse',
        'csh_out',
        'crdt_sale',
        'crdt_pymnt',
        'unit_lock',
        'unit_lock_approve',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'role_id', 'id');
    }

    /**
     * Attribute set granting every permission flag.
     *
     * Shared by the RoleFactory `admin()` state (tests) and the
     * `apex:create-admin` artisan command (production first-time setup).
     * Callers supply `name`, `status`, and `user_id`.
     *
     * @return array<string, bool|int>
     */
    public static function fullAccessFlags(): array
    {
        return [
            'pos' => 3,
            'delete_items' => true,
            'rfnd' => true,
            'discounts' => true,
            'print' => true,
            'bck_offc' => true,
            'sls' => true,
            'itms' => true, 'itms_read' => true, 'itms_create' => true, 'itms_update' => true, 'itms_delete' => true,
            'adjstmnts' => true, 'adjstmnts_read' => true, 'adjstmnts_create' => true, 'adjstmnts_update' => true, 'adjstmnts_delete' => true,
            'trnsfrs' => true, 'trnsfrs_read' => true, 'trnsfrs_create' => true, 'trnsfrs_update' => true, 'trnsfrs_delete' => true,
            'emplys' => true, 'emplys_read' => true, 'emplys_create' => true, 'emplys_update' => true, 'emplys_delete' => true,
            'rl' => true, 'rl_read' => true, 'rl_create' => true, 'rl_update' => true, 'rl_delete' => true,
            'cstmr' => true, 'cstmr_read' => true, 'cstmr_create' => true, 'cstmr_update' => true, 'cstmr_delete' => true,
            'str' => true, 'str_read' => true, 'str_create' => true, 'str_update' => true, 'str_delete' => true,
            'tax' => true, 'tax_read' => true, 'tax_create' => true, 'tax_update' => true, 'tax_delete' => true,
            'sttngs' => true,
            'prchs' => true, 'prchs_read' => true, 'prchs_create' => true, 'prchs_update' => true, 'prchs_delete' => true, 'prchs_approve' => true,
            'invntry' => true, 'invntry_read' => true, 'invntry_create' => true, 'invntry_update' => true, 'invntry_delete' => true,
            'spplrs' => true, 'spplrs_read' => true, 'spplrs_create' => true, 'spplrs_update' => true, 'spplrs_delete' => true,
            'attndnc' => true, 'attndnc_read' => true, 'attndnc_create' => true, 'attndnc_update' => true, 'attndnc_delete' => true, 'attndnc_schedules' => true,
            'bnkng' => true, 'bnkng_read' => true, 'bnkng_create' => true, 'bnkng_update' => true, 'bnkng_delete' => true,
            'expnss' => true, 'expnss_read' => true, 'expnss_create' => true, 'expnss_update' => true, 'expnss_delete' => true,
            'pulse' => true,
            'csh_out' => true,
            'crdt_sale' => true,
            'crdt_pymnt' => true,
            'unit_lock' => true,
            'unit_lock_approve' => true,
        ];
    }
}
