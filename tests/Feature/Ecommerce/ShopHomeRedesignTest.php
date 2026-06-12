<?php

namespace Tests\Feature\Ecommerce;

use App\Livewire\Ecommerce\AddToCartButton;
use App\Models\Ecommerce\ShopAnnouncement;
use App\Models\Products\Category;
use App\Models\Products\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopHomeRedesignTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_renders_redesigned_sections(): void
    {
        $response = $this->get('/shop');

        $response->assertOk();
        $response->assertSee('How It Works');
        $response->assertSee('Browse &amp; Add', false);
        $response->assertSee('Order &amp; Pay', false);
        $response->assertSee('Pick Up or Receive');
        $response->assertSee('Same-day Pickup');
        $response->assertSee('Ready to fill your basket?');
        $response->assertSee('Why Choose');
    }

    public function test_default_hero_shows_search_and_live_stats(): void
    {
        $category = Category::factory()->create(['status' => true]);
        Item::factory()->count(3)->create(['status' => true, 'category_id' => $category->id]);
        Item::factory()->create(['status' => false, 'category_id' => $category->id]);

        $response = $this->get('/shop');

        $response->assertOk();
        $response->assertSee('class="qb-hero-search"', false);
        $response->assertSee(route('shops.products.index'));
        $response->assertSee('<div class="num">3+</div>', false);
        $response->assertSee('<div class="num">1</div>', false);
    }

    public function test_featured_products_render_add_to_cart_buttons(): void
    {
        $category = Category::factory()->create(['status' => true]);
        Item::factory()->create([
            'name' => 'Spotlight Soap',
            'status' => true,
            'featured' => true,
            'category_id' => $category->id,
            'price' => 49.75,
        ]);

        $response = $this->get('/shop');

        $response->assertOk();
        $response->assertSeeLivewire(AddToCartButton::class);
        $response->assertSee('Spotlight Soap');
        $response->assertSee('49.75');
    }

    public function test_featured_product_with_zero_price_falls_back_to_cost_plus_markup(): void
    {
        $category = Category::factory()->create(['status' => true]);
        Item::factory()->create([
            'name' => 'Markup Item',
            'status' => true,
            'featured' => true,
            'category_id' => $category->id,
            'price' => 0,
            'cost' => 100,
            'markup' => 25,
        ]);

        $response = $this->get('/shop');

        $response->assertOk();
        $response->assertSee('125.00');
    }

    /**
     * Regression: the perks strip used a -34px negative margin that pulled it
     * over the hero/announcement banner, and the featured rail clipped the
     * hover lift of its cards — both read as broken overlaps to customers.
     */
    public function test_perks_strip_does_not_overlap_banner_and_rail_has_clip_headroom(): void
    {
        ShopAnnouncement::factory()->hero()->create(['title' => 'Overlap Check Banner']);

        $response = $this->get('/shop');

        $response->assertOk();
        $response->assertDontSee('margin-top: -34px', false);
        $response->assertSee('padding: 16px 16px 30px', false);
        $response->assertSee('margin: -12px -12px -16px', false);
    }

    public function test_hero_announcement_carousel_replaces_default_hero(): void
    {
        ShopAnnouncement::factory()->hero()->create(['title' => 'Mega Sale Week']);

        $response = $this->get('/shop');

        $response->assertOk();
        $response->assertSee('Mega Sale Week');
        $response->assertSee('announcementCarousel');
        $response->assertDontSee('class="qb-hero-search"', false);
    }

    public function test_products_page_accepts_search_query_from_url(): void
    {
        $category = Category::factory()->create(['status' => true]);
        Item::factory()->create(['name' => 'Jasmine Rice 5kg', 'status' => true, 'category_id' => $category->id]);
        Item::factory()->create(['name' => 'Dish Soap', 'status' => true, 'category_id' => $category->id]);

        $response = $this->get('/shop/products?query=Jasmine');

        $response->assertOk();
        $response->assertSee('Jasmine Rice 5kg');
        $response->assertDontSee('Dish Soap');
    }
}
