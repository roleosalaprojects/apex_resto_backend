<?php

namespace Tests\Feature\Bir;

use App\Models\Employees\Role;
use App\Models\Pos\Sale;
use App\Models\Settings\Pos;
use App\Models\Settings\Store;
use App\Models\User;
use App\Services\DocumentNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentSeriesTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Pos $pos;

    protected DocumentNumberService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $role = Role::factory()->admin()->create();
        $this->user = User::factory()->create(['role_id' => $role->id, 'user_id' => 1]);
        $store = Store::factory()->create();
        $this->pos = Pos::create([
            'name' => 'POS', 'store_id' => $store->id, 'status' => true,
            'mac' => '00:00:00:00:00:00', 'number' => 1, 'user_id' => $this->user->id, 'reset_counter' => 1,
        ]);
        $this->service = app(DocumentNumberService::class);
    }

    public function test_sale_counter_starts_at_base_and_increments(): void
    {
        $first = $this->service->nextSaleNumbers($this->pos);
        $this->assertEquals(100000, $first['counter']);
        $this->assertEquals(1, $first['txn_no']);

        Sale::factory()->create(['pos_id' => $this->pos->id, 'type' => false, 'counter' => $first['counter']]);

        $second = $this->service->nextSaleNumbers($this->pos);
        $this->assertEquals(100001, $second['counter']);
        $this->assertEquals(2, $second['txn_no']);
    }

    public function test_txn_numbers_are_gapless_under_repeated_allocation(): void
    {
        $txns = [];
        for ($i = 0; $i < 25; $i++) {
            $numbers = $this->service->nextSaleNumbers($this->pos);
            $txns[] = $numbers['txn_no'];
            Sale::factory()->create([
                'pos_id' => $this->pos->id,
                'type' => false,
                'counter' => $numbers['counter'],
            ]);
        }

        $this->assertEquals(range(1, 25), $txns);
        $this->assertEquals(25, $this->pos->fresh()->txn_counter);
    }

    public function test_void_and_return_numbers_have_independent_series(): void
    {
        $this->assertEquals(1, $this->service->nextVoidNumber($this->pos));
        $this->assertEquals(2, $this->service->nextVoidNumber($this->pos));
        $this->assertEquals(1, $this->service->nextReturnNumber($this->pos));
        $this->assertEquals(2, $this->service->nextReturnNumber($this->pos));

        $this->assertEquals(2, $this->pos->fresh()->void_counter);
        $this->assertEquals(2, $this->pos->fresh()->return_counter);
    }

    public function test_refund_draws_return_number_and_r_prefix(): void
    {
        $numbers = $this->service->nextSaleNumbers($this->pos, false, 123);

        $this->assertEquals('R', $numbers['son_type']);
        $this->assertEquals(1000000, $numbers['counter']);
        $this->assertEquals(1, $numbers['return_no']);
    }

    public function test_training_sales_use_separate_counter_and_dont_touch_official_series(): void
    {
        $official = $this->service->nextSaleNumbers($this->pos);
        Sale::factory()->create(['pos_id' => $this->pos->id, 'type' => false, 'counter' => $official['counter']]);

        $training = $this->service->nextSaleNumbers($this->pos, true);
        $this->assertEquals('TR', $training['son_type']);
        $this->assertEquals(1, $training['counter']);
        $this->assertEquals(1, $this->pos->fresh()->training_counter);

        // Official series unaffected by the training transaction.
        $next = $this->service->nextSaleNumbers($this->pos);
        $this->assertEquals(100001, $next['counter']);
    }

    public function test_record_reprint_increments_count_and_stamps_audit(): void
    {
        $sale = Sale::factory()->create(['pos_id' => $this->pos->id, 'type' => false, 'reprint_count' => 0]);

        $this->service->recordReprint($sale, $this->user->id);
        $this->service->recordReprint($sale->fresh(), $this->user->id);

        $sale->refresh();
        $this->assertEquals(2, $sale->reprint_count);
        $this->assertNotNull($sale->last_reprinted_at);
        $this->assertEquals($this->user->id, $sale->last_reprinted_by);
    }
}
