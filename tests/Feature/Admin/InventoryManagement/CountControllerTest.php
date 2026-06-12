<?php

namespace Tests\Feature\Admin\InventoryManagement;

use App\Models\Employees\Role;
use App\Models\InventoryManagement\Count;
use App\Models\InventoryManagement\CountLine;
use App\Models\Products\Item;
use App\Models\Products\ItemStore;
use App\Models\Settings\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CountControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Role $role;

    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->role = Role::factory()->admin()->create(['user_id' => 1]);
        $this->user = User::factory()->create([
            'user_id' => 1,
            'role_id' => $this->role->id,
        ]);
        $this->store = Store::factory()->create(['user_id' => 1]);
    }

    public function test_can_update_count_line_counted_qty(): void
    {
        $count = Count::factory()->inProgress()->create([
            'user_id' => 1,
            'created_by' => $this->user->id,
            'store_id' => $this->store->id,
        ]);

        $item = Item::factory()->create(['user_id' => 1]);
        $line = CountLine::factory()->create([
            'count_id' => $count->id,
            'item_id' => $item->id,
            'counted_qty' => null,
        ]);

        $response = $this->actingAs($this->user)
            ->patchJson(route('counts.update-line', $count->id), [
                'line_id' => $line->id,
                'counted_qty' => 25.50,
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('count_lines', [
            'id' => $line->id,
            'counted_qty' => 25.50,
        ]);
    }

    public function test_cannot_update_count_line_on_finalized_count(): void
    {
        $count = Count::factory()->completed()->create([
            'user_id' => 1,
            'created_by' => $this->user->id,
            'store_id' => $this->store->id,
        ]);

        $item = Item::factory()->create(['user_id' => 1]);
        $line = CountLine::factory()->create([
            'count_id' => $count->id,
            'item_id' => $item->id,
            'counted_qty' => 10.00,
        ]);

        $response = $this->actingAs($this->user)
            ->patchJson(route('counts.update-line', $count->id), [
                'line_id' => $line->id,
                'counted_qty' => 30.00,
            ]);

        $response->assertStatus(400)
            ->assertJson(['success' => false, 'message' => 'Count already finalized']);
    }

    public function test_can_finalize_count_with_all_items_counted(): void
    {
        $count = Count::factory()->inProgress()->create([
            'user_id' => 1,
            'created_by' => $this->user->id,
            'store_id' => $this->store->id,
        ]);

        $item = Item::factory()->create(['user_id' => 1]);

        ItemStore::factory()->create([
            'item_id' => $item->id,
            'store_id' => $this->store->id,
            'stock' => 10.00,
        ]);

        CountLine::factory()->counted(15.00)->create([
            'count_id' => $count->id,
            'item_id' => $item->id,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('counts.finalize', $count->id));

        $response->assertRedirect(route('counts.show', $count->id));

        $this->assertDatabaseHas('counts', [
            'id' => $count->id,
            'status' => 2,
        ]);

        $this->assertDatabaseHas('item_stores', [
            'item_id' => $item->id,
            'store_id' => $this->store->id,
            'stock' => 15.00,
        ]);
    }

    public function test_cannot_finalize_count_with_uncounted_items(): void
    {
        $count = Count::factory()->inProgress()->create([
            'user_id' => 1,
            'created_by' => $this->user->id,
            'store_id' => $this->store->id,
        ]);

        $item = Item::factory()->create(['user_id' => 1]);
        CountLine::factory()->create([
            'count_id' => $count->id,
            'item_id' => $item->id,
            'counted_qty' => null,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('counts.finalize', $count->id));

        $response->assertRedirect(route('counts.show', $count->id));

        $this->assertDatabaseHas('counts', [
            'id' => $count->id,
            'status' => 1,
        ]);
    }

    public function test_user_without_permission_cannot_update_count_line(): void
    {
        $restrictedRole = Role::factory()->create([
            'user_id' => 1,
            'invntry' => true,
            'invntry_read' => true,
            'invntry_update' => false,
        ]);

        $restrictedUser = User::factory()->create([
            'user_id' => 1,
            'role_id' => $restrictedRole->id,
        ]);

        $count = Count::factory()->inProgress()->create([
            'user_id' => 1,
            'created_by' => $this->user->id,
            'store_id' => $this->store->id,
        ]);

        $item = Item::factory()->create(['user_id' => 1]);
        $line = CountLine::factory()->create([
            'count_id' => $count->id,
            'item_id' => $item->id,
        ]);

        $response = $this->actingAs($restrictedUser)
            ->patchJson(route('counts.update-line', $count->id), [
                'line_id' => $line->id,
                'counted_qty' => 25.00,
            ]);

        $response->assertStatus(403);
    }
}
