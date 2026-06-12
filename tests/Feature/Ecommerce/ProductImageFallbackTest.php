<?php

namespace Tests\Feature\Ecommerce;

use App\Models\Products\Category;
use App\Models\Products\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductImageFallbackTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::factory()->create([
            'status' => true,
            'icon' => '🍚',
        ]);
    }

    public function test_display_image_url_is_null_when_image_not_set(): void
    {
        $item = Item::factory()->create(['image' => null, 'category_id' => $this->category->id]);

        $this->assertNull($item->displayImageUrl());
    }

    public function test_display_image_url_is_null_when_file_missing_from_disk(): void
    {
        $item = Item::factory()->create([
            'image' => 'img/products/does-not-exist-anymore.jpg',
            'category_id' => $this->category->id,
        ]);

        $this->assertNull($item->displayImageUrl());
    }

    public function test_display_image_url_returns_asset_url_when_file_exists(): void
    {
        $item = Item::factory()->create([
            'image' => 'assets/media/logos/favicon.ico',
            'category_id' => $this->category->id,
        ]);

        $this->assertSame(asset('assets/media/logos/favicon.ico'), $item->displayImageUrl());
    }

    public function test_display_image_url_passes_through_absolute_urls(): void
    {
        $item = Item::factory()->create([
            'image' => 'https://cdn.example.com/rice.jpg',
            'category_id' => $this->category->id,
        ]);

        $this->assertSame('https://cdn.example.com/rice.jpg', $item->displayImageUrl());
    }

    public function test_homepage_featured_item_with_missing_file_shows_branded_placeholder(): void
    {
        Item::factory()->create([
            'name' => 'Ghost Image Rice',
            'status' => true,
            'featured' => true,
            'image' => 'img/products/missing.jpg',
            'category_id' => $this->category->id,
        ]);

        $response = $this->get('/shop');

        $response->assertOk();
        $response->assertSee('qb-img-placeholder');
        $response->assertSee('🍚');
        $response->assertDontSee('img/products/missing.jpg');
    }

    public function test_products_page_item_with_missing_file_shows_branded_placeholder(): void
    {
        Item::factory()->create([
            'name' => 'Ghost Image Soap',
            'status' => true,
            'image' => 'img/products/also-missing.jpg',
            'category_id' => $this->category->id,
        ]);

        $response = $this->get('/shop/products');

        $response->assertOk();
        $response->assertSee('qb-img-placeholder');
        $response->assertSee('🍚');
        $response->assertDontSee('img/products/also-missing.jpg');
    }

    public function test_product_show_page_with_missing_file_shows_branded_placeholder(): void
    {
        $item = Item::factory()->create([
            'status' => true,
            'image' => 'img/products/long-gone.jpg',
            'category_id' => $this->category->id,
        ]);

        $response = $this->get(route('shops.products.show', $item->id));

        $response->assertOk();
        $response->assertSee('qb-img-placeholder');
        $response->assertSee('🍚');
        $response->assertDontSee('long-gone.jpg');
    }

    public function test_placeholder_falls_back_to_basket_emoji_when_category_has_no_icon(): void
    {
        $bareCategory = Category::factory()->create(['status' => true, 'icon' => null]);
        Item::factory()->create([
            'status' => true,
            'featured' => true,
            'image' => null,
            'category_id' => $bareCategory->id,
        ]);

        $response = $this->get('/shop');

        $response->assertOk();
        $response->assertSee('qb-img-placeholder');
        $response->assertSee('🛒');
    }
}
