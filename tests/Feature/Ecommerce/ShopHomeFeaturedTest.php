<?php

namespace Tests\Feature\Ecommerce;

use App\Models\Products\Category;
use App\Models\Products\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopHomeFeaturedTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_shows_featured_categories_in_grid_when_some_are_marked(): void
    {
        Category::factory()->create(['name' => 'Featured Rice', 'status' => true, 'featured' => true, 'featured_order' => 10]);
        Category::factory()->create(['name' => 'Unfeatured Soap', 'status' => true, 'featured' => false]);

        $response = $this->get('/shop');

        $response->assertOk();
        // The unfeatured category should not appear inside the homepage's
        // qb-category-item grid blocks — though it CAN appear in the
        // navbar Categories dropdown (which is full by spec).
        $this->assertMatchesRegularExpression(
            '/qb-category-item[^>]*>[^<]*<div class="qb-category-icon">[^<]*<\/div>\s*<div class="qb-category-name">Featured Rice<\/div>/s',
            $response->getContent(),
        );
        $this->assertDoesNotMatchRegularExpression(
            '/qb-category-item[^>]*>[^<]*<div class="qb-category-icon">[^<]*<\/div>\s*<div class="qb-category-name">Unfeatured Soap<\/div>/s',
            $response->getContent(),
        );
    }

    public function test_homepage_falls_back_to_all_active_when_none_featured(): void
    {
        Category::factory()->create(['name' => 'Just Active', 'status' => true, 'featured' => false]);

        $response = $this->get('/shop');

        $response->assertOk();
        $response->assertSee('Just Active');
    }

    public function test_homepage_excludes_inactive_categories_even_when_marked_featured(): void
    {
        Category::factory()->create(['name' => 'Hidden Inactive', 'status' => false, 'featured' => true, 'featured_order' => 5]);
        Category::factory()->create(['name' => 'Visible Active', 'status' => true, 'featured' => true, 'featured_order' => 10]);

        $response = $this->get('/shop');

        $response->assertOk();
        $response->assertSee('Visible Active');
        $response->assertDontSee('Hidden Inactive');
    }

    public function test_homepage_renders_featured_products_section_when_items_marked(): void
    {
        $category = Category::factory()->create(['status' => true]);
        Item::factory()->create([
            'name' => 'Bicol Express Mix',
            'status' => true,
            'featured' => true,
            'featured_order' => 10,
            'category_id' => $category->id,
            'price' => 99.50,
        ]);

        $response = $this->get('/shop');

        $response->assertOk();
        $response->assertSee('Featured Products');
        $response->assertSee('Bicol Express Mix');
    }

    public function test_homepage_hides_featured_products_section_when_no_items_featured(): void
    {
        $category = Category::factory()->create(['status' => true]);
        Item::factory()->create([
            'status' => true,
            'featured' => false,
            'category_id' => $category->id,
        ]);

        $response = $this->get('/shop');

        $response->assertOk();
        $response->assertDontSee('Featured Products');
    }

    public function test_homepage_orders_featured_categories_by_featured_order(): void
    {
        Category::factory()->create(['name' => 'Third', 'status' => true, 'featured' => true, 'featured_order' => 30]);
        Category::factory()->create(['name' => 'First', 'status' => true, 'featured' => true, 'featured_order' => 10]);
        Category::factory()->create(['name' => 'Second', 'status' => true, 'featured' => true, 'featured_order' => 20]);

        $response = $this->get('/shop');

        $content = $response->getContent();
        $firstPos = strpos($content, 'First');
        $secondPos = strpos($content, 'Second');
        $thirdPos = strpos($content, 'Third');

        $this->assertLessThan($secondPos, $firstPos, 'First should appear before Second');
        $this->assertLessThan($thirdPos, $secondPos, 'Second should appear before Third');
    }
}
