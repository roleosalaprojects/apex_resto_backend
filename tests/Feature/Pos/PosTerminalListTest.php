<?php

namespace Tests\Feature\Pos;

use App\Models\Employees\Role;
use App\Models\Settings\Pos;
use App\Models\Settings\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class PosTerminalListTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_only_the_tenants_terminals_with_store(): void
    {
        $role = Role::factory()->admin()->create();
        $user = User::factory()->create(['role_id' => $role->id, 'user_id' => 1]);
        $store = Store::factory()->create(['name' => 'MAIN']);

        Pos::create([
            'name' => 'Terminal 1', 'store_id' => $store->id, 'status' => true,
            'mac' => 'AA', 'number' => 1, 'user_id' => 1, 'reset_counter' => 1,
        ]);
        Pos::create([
            'name' => 'Foreign Terminal', 'store_id' => $store->id, 'status' => true,
            'mac' => 'BB', 'number' => 2, 'user_id' => 99, 'reset_counter' => 1,
        ]);

        Passport::actingAs($user);

        $response = $this->getJson('/api/v1/pos-terminals')->assertStatus(200);

        $this->assertEquals(['Terminal 1'], $response->json('data.*.name'));
        $this->assertEquals('MAIN', $response->json('data.0.store.name'));
    }
}
