<?php

namespace Tests\Feature\User\Products;

use App\Models\Employees\Role;
use App\Models\Products\Category;
use App\Models\Products\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Role $role;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->role = Role::factory()->admin()->create();
        $this->user = User::factory()->create([
            'role_id' => $this->role->id,
            'user_id' => 1,
            'status' => true,
        ]);
        $this->category = Category::factory()->create([
            'status' => true,
            'user_id' => $this->user->user_id,
        ]);
    }

    public function test_can_view_items_index(): void
    {
        $response = $this->actingAs($this->user)->get('/admin/items');

        $response->assertOk();
    }

    public function test_can_view_create_item_form(): void
    {
        $response = $this->actingAs($this->user)->get('/admin/items/create');

        $response->assertOk();
    }

    public function test_can_view_item(): void
    {
        $item = Item::factory()->create([
            'status' => true,
            'category_id' => $this->category->id,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->actingAs($this->user)->get("/admin/items/{$item->id}");

        $response->assertOk();
    }

    public function test_show_renders_margin_breakdown_with_base_price_margin(): void
    {
        $item = Item::factory()->create([
            'status' => true,
            'category_id' => $this->category->id,
            'user_id' => $this->user->user_id,
            'cost' => 50,
            'price' => 75,
            'discountable' => 0,
        ]);

        $response = $this->actingAs($this->user)->get("/admin/items/{$item->id}");

        $response->assertOk();
        $response->assertSee('Margin Breakdown');
        // (75 - 50) / 50 * 100 = +50.0%
        $response->assertSee('+50.0%');
    }

    public function test_show_renders_special_discount_margins_when_discountable(): void
    {
        $item = Item::factory()->create([
            'status' => true,
            'category_id' => $this->category->id,
            'user_id' => $this->user->user_id,
            'cost' => 50,
            'price' => 100,
            'discountable' => 1,
            'senior' => 20,
            'pwd' => 20,
            'solo_parent' => 0,
            'naac' => 0,
        ]);

        $response = $this->actingAs($this->user)->get("/admin/items/{$item->id}");

        $response->assertOk();
        $response->assertSee('After Special Discount');
        $response->assertSee('Senior Citizen');
        $response->assertSee('PWD');
        // Effective = 100 * 0.8 = 80 → margin (80-50)/50 * 100 = +60.0%
        $response->assertSee('+60.0%');
    }

    public function test_show_hides_margin_breakdown_when_cost_is_zero(): void
    {
        $item = Item::factory()->create([
            'status' => true,
            'category_id' => $this->category->id,
            'user_id' => $this->user->user_id,
            'cost' => 0,
            'price' => 100,
        ]);

        $response = $this->actingAs($this->user)->get("/admin/items/{$item->id}");

        $response->assertOk();
        $response->assertSee('Margin Breakdown');
        $response->assertSee('Set a non-zero cost to see margins.');
    }

    public function test_can_view_edit_item_form(): void
    {
        $item = Item::factory()->create([
            'status' => true,
            'category_id' => $this->category->id,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->actingAs($this->user)->get("/admin/items/{$item->id}/edit");

        $response->assertOk();
    }

    public function test_can_get_items_table_data(): void
    {
        Item::factory()->count(5)->create([
            'status' => true,
            'category_id' => $this->category->id,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->actingAs($this->user)
            ->get('/admin/items/table');

        $response->assertStatus(200);
    }

    public function test_can_select_items(): void
    {
        Item::factory()->count(3)->create([
            'status' => true,
            'category_id' => $this->category->id,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->actingAs($this->user)
            ->get('/admin/items/select?term=');

        $response->assertStatus(200);
    }

    public function test_can_get_item_details(): void
    {
        $item = Item::factory()->create([
            'status' => true,
            'category_id' => $this->category->id,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->actingAs($this->user)
            ->get("/admin/items/get/{$item->id}");

        $response->assertStatus(200);
    }

    public function test_can_check_barcode(): void
    {
        Item::factory()->create([
            'barcode' => '1234567890123',
            'status' => true,
            'category_id' => $this->category->id,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->actingAs($this->user)
            ->get('/admin/products/checkBarcode?code=1234567890123');

        $response->assertStatus(200);
    }

    public function test_unauthenticated_user_cannot_access_items(): void
    {
        $response = $this->get('/admin/items');

        $response->assertRedirect('/admin/login');
    }
}
