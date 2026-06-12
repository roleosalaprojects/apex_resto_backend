<?php

namespace Tests\Feature\API\v1\pos;

use App\Models\Employees\Role;
use App\Models\Products\Category;
use App\Models\Products\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
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
        ]);
        $this->category = Category::factory()->create(['status' => true]);
    }

    public function test_can_list_items(): void
    {
        Item::factory()->count(3)->create([
            'status' => true,
            'category_id' => $this->category->id,
        ]);

        Passport::actingAs($this->user);

        $response = $this->getJson('/api/v1/items');

        $response->assertStatus(200);
    }

    public function test_can_search_items_by_name(): void
    {
        Item::factory()->create([
            'name' => 'COCA COLA',
            'status' => true,
            'category_id' => $this->category->id,
        ]);
        Item::factory()->create([
            'name' => 'PEPSI',
            'status' => true,
            'category_id' => $this->category->id,
        ]);

        Passport::actingAs($this->user);

        $response = $this->getJson('/api/v1/items?term=coca');

        $response->assertStatus(200);
    }

    public function test_can_search_items_by_barcode(): void
    {
        Item::factory()->create([
            'barcode' => '1234567890123',
            'status' => true,
            'category_id' => $this->category->id,
        ]);

        Passport::actingAs($this->user);

        $response = $this->getJson('/api/v1/items?term=1234567890123');

        $response->assertStatus(200);
    }

    public function test_can_show_item(): void
    {
        $item = Item::factory()->create([
            'status' => true,
            'category_id' => $this->category->id,
        ]);

        Passport::actingAs($this->user);

        $response = $this->getJson("/api/v1/items/{$item->id}");

        $response->assertStatus(200);
    }

    public function test_can_get_items_by_ids(): void
    {
        $items = Item::factory()->count(3)->create([
            'status' => true,
            'category_id' => $this->category->id,
        ]);

        Passport::actingAs($this->user);

        $itemIds = $items->pluck('id')->mapWithKeys(fn ($id) => [$id => true])->toArray();

        $response = $this->getJson('/api/v1/items/get?'.http_build_query($itemIds));

        $response->assertStatus(200);
    }

    public function test_can_search_items_from_key(): void
    {
        Item::factory()->create([
            'name' => 'TEST PRODUCT',
            'status' => true,
            'category_id' => $this->category->id,
        ]);

        Passport::actingAs($this->user);

        $response = $this->getJson('/api/v1/items/search?term=test');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'products',
            ],
        ]);
    }

    public function test_unauthenticated_user_cannot_access_items(): void
    {
        $response = $this->getJson('/api/v1/items');

        $response->assertStatus(401);
    }
}
