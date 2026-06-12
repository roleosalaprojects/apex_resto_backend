<?php

namespace Tests\Feature\Ecommerce;

use App\Models\Products\Category;
use App\Models\Products\Item;
use App\Models\Products\ItemStore;
use App\Models\Settings\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NavbarCategoryDropdownTest extends TestCase
{
    use RefreshDatabase;

    protected Category $categoryA;

    protected Category $categoryB;

    protected function setUp(): void
    {
        parent::setUp();

        $store = Store::factory()->create(['status' => true]);

        $this->categoryA = Category::factory()->create(['name' => 'Fruits', 'icon' => null]);
        $this->categoryB = Category::factory()->create(['name' => 'Beverages', 'icon' => null]);

        $itemA = Item::factory()->create([
            'name' => 'Apple',
            'status' => true,
            'category_id' => $this->categoryA->id,
        ]);

        ItemStore::factory()->create([
            'item_id' => $itemA->id,
            'store_id' => $store->id,
            'stock' => 10,
            'status' => true,
        ]);
    }

    public function test_navbar_shows_categories_dropdown_on_products_page(): void
    {
        $this->get('/shop/products')
            ->assertStatus(200)
            ->assertSee('Categories')
            ->assertSee('Fruits')
            ->assertSee('Beverages');
    }

    public function test_navbar_shows_categories_dropdown_on_home_page(): void
    {
        $this->get('/shop')
            ->assertStatus(200)
            ->assertSee('Categories');
    }

    public function test_navbar_category_links_to_products_with_filter(): void
    {
        $this->get('/shop/products')
            ->assertStatus(200)
            ->assertSee(route('shops.products.index', ['category' => $this->categoryA->id]));
    }

    public function test_products_page_shows_category_name_when_filtered(): void
    {
        Livewire::test(\App\Livewire\Ecommerce\ProductPage::class, ['category' => $this->categoryA->id])
            ->assertSee('Category: Fruits');
    }

    public function test_products_page_can_clear_category_filter(): void
    {
        Livewire::test(\App\Livewire\Ecommerce\ProductPage::class, ['category' => $this->categoryA->id])
            ->assertSee('Category: Fruits')
            ->call('clearCategory')
            ->assertDontSee('Category: Fruits');
    }

    public function test_inactive_categories_not_shown_in_navbar(): void
    {
        $inactiveCategory = Category::factory()->create(['name' => 'Hidden Category', 'status' => false]);

        $this->get('/shop/products')
            ->assertStatus(200)
            ->assertDontSee('Hidden Category');
    }

    public function test_category_with_icon_shows_icon_in_navbar(): void
    {
        $this->categoryA->update(['icon' => '🍎']);

        $this->get('/shop/products')
            ->assertStatus(200)
            ->assertSee('🍎');
    }
}
