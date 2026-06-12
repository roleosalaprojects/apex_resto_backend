<?php

namespace Tests\Feature\Customer;

use App\Livewire\Ecommerce\CustomerOrders;
use App\Models\CustomerRelations\Customer;
use App\Models\Ecommerce\EcommerceOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerOrdersFilterTest extends TestCase
{
    use RefreshDatabase;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = Customer::factory()->create();
    }

    private function makeOrder(array $overrides = []): EcommerceOrder
    {
        return EcommerceOrder::create(array_merge([
            'reference' => 'ECO-'.strtoupper(substr(md5(uniqid('', true)), 0, 8)),
            'customer_id' => $this->customer->id,
            'total' => 100,
            'qty' => 1,
            'status' => EcommerceOrder::STATUS_PENDING,
            'is_wholesale' => false,
        ], $overrides));
    }

    public function test_search_matches_reference_substring(): void
    {
        $target = $this->makeOrder(['reference' => 'ECO-ABC12345']);
        $other = $this->makeOrder(['reference' => 'ECO-XYZ99999']);

        Livewire::actingAs($this->customer, 'customer')
            ->test(CustomerOrders::class)
            ->set('search', 'ABC12345')
            ->assertSee($target->reference)
            ->assertDontSee($other->reference);
    }

    public function test_search_matches_note_substring(): void
    {
        $target = $this->makeOrder(['note' => 'please pack carefully']);
        $other = $this->makeOrder(['note' => 'nothing special']);

        Livewire::actingAs($this->customer, 'customer')
            ->test(CustomerOrders::class)
            ->set('search', 'carefully')
            ->assertSee($target->reference)
            ->assertDontSee($other->reference);
    }

    public function test_status_pill_filters_results(): void
    {
        $paid = $this->makeOrder(['status' => EcommerceOrder::STATUS_PAID]);
        $pending = $this->makeOrder(['status' => EcommerceOrder::STATUS_PENDING]);

        Livewire::actingAs($this->customer, 'customer')
            ->test(CustomerOrders::class)
            ->set('status', 'paid')
            ->assertSee($paid->reference)
            ->assertDontSee($pending->reference);
    }

    public function test_sort_total_high_orders_descending(): void
    {
        $small = $this->makeOrder(['total' => 50]);
        $big = $this->makeOrder(['total' => 5000]);

        $component = Livewire::actingAs($this->customer, 'customer')
            ->test(CustomerOrders::class)
            ->set('sort', 'total_high');

        $orderRefs = collect($component->viewData('orders')->items())
            ->pluck('reference')
            ->all();

        $this->assertSame([$big->reference, $small->reference], $orderRefs);
    }

    public function test_other_customers_orders_are_excluded(): void
    {
        $mine = $this->makeOrder();
        $theirs = EcommerceOrder::create([
            'reference' => 'ECO-OTHERCUST',
            'customer_id' => Customer::factory()->create()->id,
            'total' => 100,
            'qty' => 1,
            'status' => EcommerceOrder::STATUS_PENDING,
            'is_wholesale' => false,
        ]);

        Livewire::actingAs($this->customer, 'customer')
            ->test(CustomerOrders::class)
            ->assertSee($mine->reference)
            ->assertDontSee($theirs->reference);
    }

    public function test_clear_filters_resets_state(): void
    {
        $this->makeOrder();

        Livewire::actingAs($this->customer, 'customer')
            ->test(CustomerOrders::class)
            ->set('search', 'NOMATCH')
            ->set('status', 'paid')
            ->set('sort', 'oldest')
            ->call('clearFilters')
            ->assertSet('search', '')
            ->assertSet('status', 'all')
            ->assertSet('sort', 'newest');
    }
}
