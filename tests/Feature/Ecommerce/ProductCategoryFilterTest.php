<?php

namespace Tests\Feature\Ecommerce;

use App\Models\Products\Category;
use App\Models\Products\Item;
use App\Models\Products\ItemStore;
use App\Models\Settings\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductCategoryFilterTest extends TestCase
{
    use RefreshDatabase;

    protected Category $categoryA;

    protected Category $categoryB;

    protected Item $itemA;

    protected Item $itemB;

    protected function setUp(): void
    {
        parent::setUp();

        $store = Store::factory()->create(['status' => true]);

        $this->categoryA = Category::factory()->create(['name' => 'Fruits']);
        $this->categoryB = Category::factory()->create(['name' => 'Beverages']);

        $this->itemA = Item::factory()->create([
            'name' => 'Apple',
            'status' => true,
            'category_id' => $this->categoryA->id,
        ]);

        $this->itemB = Item::factory()->create([
            'name' => 'Orange Juice',
            'status' => true,
            'category_id' => $this->categoryB->id,
        ]);

        ItemStore::factory()->create([
            'item_id' => $this->itemA->id,
            'store_id' => $store->id,
            'stock' => 10,
            'status' => true,
        ]);

        ItemStore::factory()->create([
            'item_id' => $this->itemB->id,
            'store_id' => $store->id,
            'stock' => 10,
            'status' => true,
        ]);
    }

    public function test_products_page_shows_all_products_without_filter(): void
    {
        Livewire::test(\App\Livewire\Ecommerce\ProductPage::class)
            ->assertSee('Apple')
            ->assertSee('Orange Juice');
    }

    public function test_products_page_filters_by_category(): void
    {
        Livewire::test(\App\Livewire\Ecommerce\ProductPage::class)
            ->call('filterCategory', $this->categoryA->id)
            ->assertSee('Apple')
            ->assertDontSee('Orange Juice');
    }

    public function test_products_page_filters_by_other_category(): void
    {
        Livewire::test(\App\Livewire\Ecommerce\ProductPage::class)
            ->call('filterCategory', $this->categoryB->id)
            ->assertDontSee('Apple')
            ->assertSee('Orange Juice');
    }

    public function test_clear_category_shows_all_products(): void
    {
        Livewire::test(\App\Livewire\Ecommerce\ProductPage::class)
            ->call('filterCategory', $this->categoryA->id)
            ->assertDontSee('Orange Juice')
            ->call('clearCategory')
            ->assertSee('Apple')
            ->assertSee('Orange Juice');
    }

    public function test_invalid_category_is_ignored(): void
    {
        Livewire::test(\App\Livewire\Ecommerce\ProductPage::class)
            ->call('filterCategory', 99999)
            ->assertSee('Apple')
            ->assertSee('Orange Juice');
    }

    public function test_home_page_shows_categories(): void
    {
        $this->get('/shop')
            ->assertStatus(200)
            ->assertSee('Fruits')
            ->assertSee('Beverages');
    }

    public function test_category_links_to_products_with_filter(): void
    {
        $this->get('/shop')
            ->assertStatus(200)
            ->assertSee(route('shops.products.index', ['category' => $this->categoryA->id]));
    }

    public function test_products_page_shows_active_category_filter_label(): void
    {
        Livewire::test(\App\Livewire\Ecommerce\ProductPage::class)
            ->assertDontSee('Category: Fruits')
            ->call('filterCategory', $this->categoryA->id)
            ->assertSee('Category: Fruits');
    }

    public function test_category_search_shows_all_categories_by_default(): void
    {
        Livewire::test(\App\Livewire\Ecommerce\CategorySearch::class)
            ->assertSee('Fruits')
            ->assertSee('Beverages');
    }

    public function test_category_search_filters_by_name(): void
    {
        Livewire::test(\App\Livewire\Ecommerce\CategorySearch::class)
            ->set('search', 'Fru')
            ->assertSee('Fruits')
            ->assertDontSee('Beverages');
    }

    public function test_category_search_shows_no_results_message(): void
    {
        Livewire::test(\App\Livewire\Ecommerce\CategorySearch::class)
            ->set('search', 'Nonexistent')
            ->assertDontSee('Fruits')
            ->assertDontSee('Beverages')
            ->assertSee('No categories found');
    }

    public function test_category_search_clearing_shows_all(): void
    {
        Livewire::test(\App\Livewire\Ecommerce\CategorySearch::class)
            ->set('search', 'Fru')
            ->assertDontSee('Beverages')
            ->set('search', '')
            ->assertSee('Fruits')
            ->assertSee('Beverages');
    }
}
