<?php

namespace Tests\Feature\Admin;

use App\Models\Employees\Role;
use App\Models\Products\Category;
use App\Models\Products\Item;
use App\Models\Products\WholesalePriceTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WholesalePriceTierTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->admin()->create();
        $this->user = User::factory()->create([
            'role_id' => $role->id,
            'user_id' => 1,
            'status' => true,
        ]);

        $this->item = Item::factory()->create([
            'price' => 100.00,
            'category_id' => Category::factory()->create()->id,
        ]);
    }

    public function test_can_list_tiers_for_item(): void
    {
        WholesalePriceTier::factory()->create([
            'item_id' => $this->item->id,
            'min_qty' => 12,
            'discount' => 20.00,
        ]);

        WholesalePriceTier::factory()->create([
            'item_id' => $this->item->id,
            'min_qty' => 50,
            'discount' => 30.00,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('items.wholesale-tiers.index', $this->item));

        $response->assertOk()
            ->assertJsonCount(2);
    }

    public function test_can_create_tier(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('items.wholesale-tiers.store', $this->item), [
                'min_qty' => 12,
                'discount' => 20.00,
            ]);

        $response->assertCreated();

        $this->assertDatabaseHas('wholesale_price_tiers', [
            'item_id' => $this->item->id,
            'min_qty' => 12,
            'discount' => '20.00',
        ]);
    }

    public function test_can_update_tier(): void
    {
        $tier = WholesalePriceTier::factory()->create([
            'item_id' => $this->item->id,
            'min_qty' => 12,
            'discount' => 20.00,
        ]);

        $response = $this->actingAs($this->user)
            ->putJson(route('items.wholesale-tiers.update', $tier), [
                'min_qty' => 15,
                'discount' => 25.00,
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('wholesale_price_tiers', [
            'id' => $tier->id,
            'min_qty' => 15,
            'discount' => '25.00',
        ]);
    }

    public function test_can_delete_tier(): void
    {
        $tier = WholesalePriceTier::factory()->create([
            'item_id' => $this->item->id,
            'min_qty' => 12,
            'discount' => 20.00,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson(route('items.wholesale-tiers.destroy', $tier));

        $response->assertOk();

        $this->assertDatabaseMissing('wholesale_price_tiers', [
            'id' => $tier->id,
        ]);
    }

    public function test_duplicate_min_qty_is_rejected(): void
    {
        WholesalePriceTier::factory()->create([
            'item_id' => $this->item->id,
            'min_qty' => 12,
            'discount' => 20.00,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson(route('items.wholesale-tiers.store', $this->item), [
                'min_qty' => 12,
                'discount' => 25.00,
            ]);

        $response->assertStatus(422);
    }

    public function test_validation_rules_enforced(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('items.wholesale-tiers.store', $this->item), [
                'min_qty' => 0,
                'discount' => -5,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['min_qty', 'discount']);
    }

    public function test_validation_requires_fields(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('items.wholesale-tiers.store', $this->item), []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['min_qty', 'discount']);
    }

    public function test_tiers_returned_in_order(): void
    {
        WholesalePriceTier::factory()->create([
            'item_id' => $this->item->id,
            'min_qty' => 50,
            'discount' => 30.00,
        ]);

        WholesalePriceTier::factory()->create([
            'item_id' => $this->item->id,
            'min_qty' => 1,
            'discount' => 10.00,
        ]);

        WholesalePriceTier::factory()->create([
            'item_id' => $this->item->id,
            'min_qty' => 12,
            'discount' => 20.00,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('items.wholesale-tiers.index', $this->item));

        $response->assertOk();

        $data = $response->json();
        $this->assertEquals(1, $data[0]['min_qty']);
        $this->assertEquals(12, $data[1]['min_qty']);
        $this->assertEquals(50, $data[2]['min_qty']);
    }
}
