<?php

namespace Tests\Feature\Pos;

use App\Models\Employees\Role;
use App\Models\Products\Category;
use App\Models\Products\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class MenuVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Item $dish;

    protected Item $ingredient;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->admin()->create();
        $this->user = User::factory()->create(['role_id' => $role->id, 'user_id' => 1]);
        $category = Category::factory()->create(['status' => true]);

        $this->dish = Item::factory()->create([
            'name' => 'SISIG',
            'price' => 340,
            'status' => true,
            'category_id' => $category->id,
        ]);
        $this->ingredient = Item::factory()->create([
            'name' => 'PORK JOWL',
            'price' => 0,
            'status' => true,
            'show_in_pos' => false,
            'category_id' => $category->id,
        ]);

        Passport::actingAs($this->user);
    }

    public function test_pos_item_index_excludes_stock_only_items(): void
    {
        $names = $this->getJson('/api/v1/items?term=')
            ->assertStatus(200)
            ->json('data.*.name');

        $this->assertContains('SISIG', $names);
        $this->assertNotContains('PORK JOWL', $names);
    }

    public function test_pos_item_search_excludes_stock_only_items(): void
    {
        $names = $this->getJson('/api/v1/items/search?term=PORK')
            ->assertStatus(200)
            ->json('data.products.*.name');

        $this->assertNotContains('PORK JOWL', $names);
    }

    public function test_order_product_search_excludes_stock_only_items(): void
    {
        $names = collect(
            $this->getJson('/api/v1/orders/search?keyword=')
                ->assertStatus(200)
                ->json('data'),
        )->pluck('name');

        $this->assertContains('SISIG', $names);
        $this->assertNotContains('PORK JOWL', $names);
    }

    public function test_items_default_to_visible(): void
    {
        $this->assertTrue($this->dish->fresh()->show_in_pos);

        $names = $this->getJson('/api/v1/items?term=')
            ->assertStatus(200)
            ->json('data.*.name');

        $this->assertContains('SISIG', $names);
    }
}
