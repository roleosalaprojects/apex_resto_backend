<?php

namespace Database\Factories\Employees;

use App\Models\Employees\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->jobTitle(),
            'pos' => $this->faker->numberBetween(0, 3),
            'delete_items' => $this->faker->boolean(),
            'rfnd' => $this->faker->boolean(),
            'discounts' => $this->faker->boolean(),
            'print' => $this->faker->boolean(),
            'bck_offc' => $this->faker->boolean(),
            'sls' => $this->faker->boolean(),
            'itms' => $this->faker->boolean(),
            'itms_read' => $this->faker->boolean(),
            'itms_create' => $this->faker->boolean(),
            'itms_update' => $this->faker->boolean(),
            'itms_delete' => $this->faker->boolean(),
            'adjstmnts' => $this->faker->boolean(),
            'adjstmnts_read' => $this->faker->boolean(),
            'adjstmnts_create' => $this->faker->boolean(),
            'adjstmnts_update' => $this->faker->boolean(),
            'adjstmnts_delete' => $this->faker->boolean(),
            'trnsfrs' => $this->faker->boolean(),
            'trnsfrs_read' => $this->faker->boolean(),
            'trnsfrs_create' => $this->faker->boolean(),
            'trnsfrs_update' => $this->faker->boolean(),
            'trnsfrs_delete' => $this->faker->boolean(),
            'emplys' => $this->faker->boolean(),
            'emplys_read' => $this->faker->boolean(),
            'emplys_create' => $this->faker->boolean(),
            'emplys_update' => $this->faker->boolean(),
            'emplys_delete' => $this->faker->boolean(),
            'rl' => $this->faker->boolean(),
            'rl_read' => $this->faker->boolean(),
            'rl_create' => $this->faker->boolean(),
            'rl_update' => $this->faker->boolean(),
            'rl_delete' => $this->faker->boolean(),
            'cstmr' => $this->faker->boolean(),
            'cstmr_read' => $this->faker->boolean(),
            'cstmr_create' => $this->faker->boolean(),
            'cstmr_update' => $this->faker->boolean(),
            'cstmr_delete' => $this->faker->boolean(),
            'str' => $this->faker->boolean(),
            'str_read' => $this->faker->boolean(),
            'str_create' => $this->faker->boolean(),
            'str_update' => $this->faker->boolean(),
            'str_delete' => $this->faker->boolean(),
            'tax' => $this->faker->boolean(),
            'tax_read' => $this->faker->boolean(),
            'tax_create' => $this->faker->boolean(),
            'tax_update' => $this->faker->boolean(),
            'tax_delete' => $this->faker->boolean(),
            'sttngs' => $this->faker->boolean(),
            'status' => true,
            'user_id' => 1,
            'prchs' => $this->faker->boolean(),
            'prchs_read' => $this->faker->boolean(),
            'prchs_create' => $this->faker->boolean(),
            'prchs_update' => $this->faker->boolean(),
            'prchs_delete' => $this->faker->boolean(),
            'prchs_approve' => $this->faker->boolean(),
            'invntry' => $this->faker->boolean(),
            'invntry_read' => $this->faker->boolean(),
            'invntry_create' => $this->faker->boolean(),
            'invntry_update' => $this->faker->boolean(),
            'invntry_delete' => $this->faker->boolean(),
            'spplrs' => $this->faker->boolean(),
            'spplrs_read' => $this->faker->boolean(),
            'spplrs_create' => $this->faker->boolean(),
            'spplrs_update' => $this->faker->boolean(),
            'spplrs_delete' => $this->faker->boolean(),
            'unit_lock' => $this->faker->boolean(),
            'unit_lock_approve' => $this->faker->boolean(),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => array_merge(
            ['name' => 'Admin'],
            Role::fullAccessFlags(),
        ));
    }
}
