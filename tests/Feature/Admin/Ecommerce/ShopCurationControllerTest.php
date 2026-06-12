<?php

namespace Tests\Feature\Admin\Ecommerce;

use App\Models\Employees\Role;
use App\Models\Products\Category;
use App\Models\Products\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopCurationControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $role = Role::factory()->admin()->create();
        $this->admin = User::factory()->create([
            'role_id' => $role->id,
            'user_id' => 1,
            'status' => true,
        ]);
    }

    public function test_admin_can_view_curation_index(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('shop.curation.index'));

        $response->assertOk();
    }

    public function test_categories_featured_returns_only_featured_active(): void
    {
        Category::factory()->create(['name' => 'A', 'status' => true, 'featured' => true, 'featured_order' => 20]);
        Category::factory()->create(['name' => 'B', 'status' => true, 'featured' => true, 'featured_order' => 10]);
        Category::factory()->create(['name' => 'Hidden', 'status' => true, 'featured' => false]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('shop.curation.categories.featured'));

        $response->assertOk();
        $names = array_column($response->json('data'), 'name');
        $this->assertSame(['B', 'A'], $names);
    }

    public function test_categories_search_excludes_already_featured(): void
    {
        Category::factory()->create(['name' => 'Featured', 'status' => true, 'featured' => true]);
        Category::factory()->create(['name' => 'Candidate', 'status' => true, 'featured' => false]);

        // DataTables protocol payload — Yajra needs draw + columns + search/order
        // shape to respond. The featured filter happens before Yajra ever sees
        // the request, so payload shape doesn't affect the assertion.
        $response = $this->actingAs($this->admin)
            ->getJson(route('shop.curation.categories.search', [
                'draw' => 1,
                'start' => 0,
                'length' => 25,
                'columns' => [['data' => 'name', 'searchable' => 'true']],
                'search' => ['value' => ''],
                'order' => [['column' => 0, 'dir' => 'asc']],
            ]));

        $names = array_column($response->json('data'), 'name');
        $this->assertContains('Candidate', $names);
        $this->assertNotContains('Featured', $names);
    }

    public function test_admin_can_feature_a_category(): void
    {
        $category = Category::factory()->create(['featured' => false]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('shop.curation.categories.feature', $category));

        $response->assertOk();
        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'featured' => true,
        ]);
        $this->assertNotNull(Category::find($category->id)->featured_order);
    }

    public function test_admin_can_unfeature_a_category(): void
    {
        $category = Category::factory()->create(['featured' => true, 'featured_order' => 10]);

        $response = $this->actingAs($this->admin)
            ->deleteJson(route('shop.curation.categories.unfeature', $category));

        $response->assertOk();
        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'featured' => false,
            'featured_order' => null,
        ]);
    }

    public function test_reorder_categories_rewrites_order_atomically(): void
    {
        $a = Category::factory()->create(['featured' => true, 'featured_order' => 10]);
        $b = Category::factory()->create(['featured' => true, 'featured_order' => 20]);
        $c = Category::factory()->create(['featured' => true, 'featured_order' => 30]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('shop.curation.categories.reorder'), [
                'ids' => [$c->id, $a->id, $b->id],
            ]);

        $response->assertOk();
        $this->assertSame(10, Category::find($c->id)->featured_order);
        $this->assertSame(20, Category::find($a->id)->featured_order);
        $this->assertSame(30, Category::find($b->id)->featured_order);
    }

    public function test_reorder_silently_skips_unfeatured_ids(): void
    {
        $featured = Category::factory()->create(['featured' => true, 'featured_order' => 50]);
        $candidate = Category::factory()->create(['featured' => false]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('shop.curation.categories.reorder'), [
                'ids' => [$candidate->id, $featured->id],
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('categories', [
            'id' => $candidate->id,
            'featured' => false,
            'featured_order' => null,
        ]);
        $this->assertSame(20, Category::find($featured->id)->featured_order);
    }

    public function test_reorder_validation_rejects_empty_payload(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson(route('shop.curation.categories.reorder'), []);

        $response->assertStatus(422);
    }

    public function test_admin_can_feature_an_item(): void
    {
        $category = Category::factory()->create(['status' => true]);
        $item = Item::factory()->create([
            'status' => true,
            'featured' => false,
            'category_id' => $category->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('shop.curation.items.feature', $item));

        $response->assertOk();
        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'featured' => true,
        ]);
    }

    public function test_unauthenticated_cannot_access_curation(): void
    {
        $response = $this->get(route('shop.curation.index'));
        $response->assertRedirect();
    }
}
