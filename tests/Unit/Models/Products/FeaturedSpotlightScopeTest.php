<?php

namespace Tests\Unit\Models\Products;

use App\Models\Employees\Role;
use App\Models\Products\Category;
use App\Models\Products\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeaturedSpotlightScopeTest extends TestCase
{
    use RefreshDatabase;

    private Role $role;

    private User $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->role = Role::factory()->admin()->create();
        $this->tenant = User::factory()->create([
            'role_id' => $this->role->id,
            'status' => true,
        ]);
    }

    public function test_category_scope_returns_only_featured_and_active(): void
    {
        Category::factory()->create(['name' => 'Featured A', 'status' => true, 'featured' => true, 'featured_order' => 10]);
        Category::factory()->create(['name' => 'Not featured', 'status' => true, 'featured' => false]);
        Category::factory()->create(['name' => 'Featured but inactive', 'status' => false, 'featured' => true, 'featured_order' => 5]);

        $names = Category::featuredSpotlight()->pluck('name')->all();

        $this->assertSame(['Featured A'], $names);
    }

    public function test_category_scope_orders_by_featured_order_nulls_last(): void
    {
        Category::factory()->create(['name' => 'B order 20', 'status' => true, 'featured' => true, 'featured_order' => 20]);
        Category::factory()->create(['name' => 'A order null', 'status' => true, 'featured' => true, 'featured_order' => null]);
        Category::factory()->create(['name' => 'C order 10', 'status' => true, 'featured' => true, 'featured_order' => 10]);

        $names = Category::featuredSpotlight()->pluck('name')->all();

        $this->assertSame(['C order 10', 'B order 20', 'A order null'], $names);
    }

    public function test_item_scope_returns_only_featured_and_active(): void
    {
        $category = Category::factory()->create(['status' => true, 'user_id' => $this->tenant->user_id]);

        Item::factory()->create([
            'name' => 'Featured A',
            'status' => true,
            'featured' => true,
            'featured_order' => 10,
            'category_id' => $category->id,
            'user_id' => $this->tenant->user_id,
        ]);
        Item::factory()->create([
            'name' => 'Not featured',
            'status' => true,
            'featured' => false,
            'category_id' => $category->id,
            'user_id' => $this->tenant->user_id,
        ]);
        Item::factory()->create([
            'name' => 'Featured but inactive',
            'status' => false,
            'featured' => true,
            'featured_order' => 5,
            'category_id' => $category->id,
            'user_id' => $this->tenant->user_id,
        ]);

        $names = Item::featuredSpotlight()->pluck('name')->all();

        $this->assertSame(['Featured A'], $names);
    }
}
