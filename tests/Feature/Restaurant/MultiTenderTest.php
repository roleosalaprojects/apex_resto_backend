<?php

namespace Tests\Feature\Restaurant;

use App\Models\Accounting\Bank;
use App\Models\Accounting\BankTransaction;
use App\Models\Bi\DailyStoreMetric;
use App\Models\Employees\Role;
use App\Models\Pos\Order;
use App\Models\Pos\Sale;
use App\Models\Pos\SalePayment;
use App\Models\Pos\Zreading;
use App\Models\Products\Category;
use App\Models\Products\Item;
use App\Models\Products\ItemStore;
use App\Models\Restaurant\RestaurantTable;
use App\Models\Settings\Pos;
use App\Models\Settings\Store;
use App\Models\User;
use App\Services\Bi\DailyAggregationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class MultiTenderTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Store $store;

    protected Pos $pos;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->admin()->create();
        $this->user = User::factory()->create(['role_id' => $role->id, 'user_id' => 1]);
        $this->store = Store::factory()->create();
        $this->pos = Pos::create([
            'name' => 'Test POS',
            'store_id' => $this->store->id,
            'status' => true,
            'mac' => '00:00:00:00:00:00',
            'number' => 1,
            'user_id' => $this->user->id,
            'reset_counter' => 1,
        ]);
        $this->category = Category::factory()->create(['status' => true]);

        Passport::actingAs($this->user);
    }

    /**
     * Open a two-line dine-in order (item A @100, item B @50) on a table.
     *
     * @return array{0: int, 1: RestaurantTable, 2: int, 3: int} [orderId, table, lineAId, lineBId]
     */
    private function openTwoLineOrder(): array
    {
        $table = RestaurantTable::factory()->create(['user_id' => 1, 'store_id' => $this->store->id]);
        $itemA = Item::factory()->create(['price' => 100, 'category_id' => $this->category->id]);
        $itemB = Item::factory()->create(['price' => 50, 'category_id' => $this->category->id]);
        ItemStore::factory()->create(['item_id' => $itemA->id, 'store_id' => $this->store->id, 'stock' => 100]);
        ItemStore::factory()->create(['item_id' => $itemB->id, 'store_id' => $this->store->id, 'stock' => 100]);

        $create = $this->postJson('/api/v1/restaurant-orders', [
            'order_type' => Order::TYPE_DINE_IN,
            'table_id' => $table->id,
            'pos_id' => $this->pos->id,
            'lines' => [
                ['item_id' => $itemA->id, 'qty' => 1, 'seat' => 1],
                ['item_id' => $itemB->id, 'qty' => 1, 'seat' => 2],
            ],
        ])->assertStatus(201);

        return [
            $create->json('data.id'),
            $table,
            $create->json('data.lines.0.id'),
            $create->json('data.lines.1.id'),
        ];
    }

    private function createBank(): Bank
    {
        return Bank::create([
            'bank_name' => 'Test Bank',
            'account_name' => 'Apex Test',
            'account_number' => '0000-1111-2222',
            'account_type' => Bank::TYPE_EWALLET,
            'opening_balance' => 5000,
            'balance' => 5000,
        ]);
    }

    public function test_settle_with_multiple_tenders_records_the_breakdown(): void
    {
        [$orderId, $table] = $this->openTwoLineOrder();

        $this->postJson("/api/v1/restaurant-orders/{$orderId}/settle", [
            'payments' => [
                ['payment_type' => Sale::PAYMENT_CASH, 'amount' => 100],
                ['payment_type' => Sale::PAYMENT_CARD, 'amount' => 50, 'reference_number' => 'CARD-001'],
            ],
        ])->assertStatus(200)->assertJsonPath('data.total', 150);

        $sale = Sale::firstOrFail();
        $this->assertEquals(Sale::PAYMENT_MULTI, (int) $sale->payment_type);
        $this->assertNull($sale->reference_number);
        $this->assertNull($sale->bank_id);
        $this->assertEquals(100.0, (float) $sale->cash);
        $this->assertEquals(0.0, (float) $sale->change);

        $tenders = $sale->payments()->orderBy('payment_type')->get();
        $this->assertCount(2, $tenders);
        $this->assertEquals(100.0, (float) $tenders->firstWhere('payment_type', Sale::PAYMENT_CASH)->amount);
        $this->assertEquals(50.0, (float) $tenders->firstWhere('payment_type', Sale::PAYMENT_CARD)->amount);
        $this->assertEquals('CARD-001', $tenders->firstWhere('payment_type', Sale::PAYMENT_CARD)->reference_number);
        $this->assertEquals(150.0, (float) $tenders->sum('amount'));

        // Fully settled: order completes, table frees.
        $order = Order::find($orderId);
        $this->assertEquals(Order::STATUS_COMPLETED, $order->status);
        $this->assertEquals(RestaurantTable::STATUS_AVAILABLE, $table->fresh()->status);
    }

    public function test_change_is_given_from_the_cash_tender(): void
    {
        [$orderId] = $this->openTwoLineOrder();

        $this->postJson("/api/v1/restaurant-orders/{$orderId}/settle", [
            'payments' => [
                ['payment_type' => Sale::PAYMENT_CASH, 'amount' => 120],
                ['payment_type' => Sale::PAYMENT_CARD, 'amount' => 50],
            ],
        ])->assertStatus(200);

        $sale = Sale::firstOrFail();
        $this->assertEquals(120.0, (float) $sale->cash);
        $this->assertEquals(20.0, (float) $sale->change);

        // The cash ROW stores the applied amount (tendered minus change),
        // so the per-tender rows still sum exactly to the sale total.
        $cashRow = $sale->payments()->where('payment_type', Sale::PAYMENT_CASH)->firstOrFail();
        $this->assertEquals(100.0, (float) $cashRow->amount);
        $this->assertEquals(150.0, (float) $sale->payments()->sum('amount'));
    }

    public function test_ewallet_tender_deposits_its_applied_amount_to_the_bank(): void
    {
        $bank = $this->createBank();
        [$orderId] = $this->openTwoLineOrder();

        $this->postJson("/api/v1/restaurant-orders/{$orderId}/settle", [
            'payments' => [
                ['payment_type' => Sale::PAYMENT_CASH, 'amount' => 50],
                ['payment_type' => Sale::PAYMENT_EWALLET, 'amount' => 100, 'bank_id' => $bank->id, 'reference_number' => 'GCASH-99'],
            ],
        ])->assertStatus(200);

        $tx = BankTransaction::where('bank_id', $bank->id)
            ->where('type', BankTransaction::TYPE_DEPOSIT)
            ->firstOrFail();
        $this->assertEquals(100.0, (float) $tx->amount);
        $this->assertEquals(5100.0, (float) $bank->fresh()->balance);
    }

    public function test_split_settle_accepts_multiple_tenders(): void
    {
        [$orderId, , $lineAId] = $this->openTwoLineOrder();

        $this->postJson("/api/v1/restaurant-orders/{$orderId}/split-settle", [
            'line_ids' => [$lineAId],
            'payments' => [
                ['payment_type' => Sale::PAYMENT_CASH, 'amount' => 60],
                ['payment_type' => Sale::PAYMENT_CARD, 'amount' => 40],
            ],
        ])->assertStatus(200)
            ->assertJsonPath('data.total', 100)
            ->assertJsonPath('data.fully_settled', false);

        $sale = Sale::firstOrFail();
        $this->assertEquals(Sale::PAYMENT_MULTI, (int) $sale->payment_type);
        $this->assertEquals(100.0, (float) $sale->payments()->sum('amount'));
    }

    public function test_settle_seat_accepts_multiple_tenders(): void
    {
        [$orderId] = $this->openTwoLineOrder();

        $this->postJson("/api/v1/restaurant-orders/{$orderId}/settle-seat", [
            'seats' => [1],
            'payments' => [
                ['payment_type' => Sale::PAYMENT_CASH, 'amount' => 70],
                ['payment_type' => Sale::PAYMENT_GIFT_CERT, 'amount' => 30, 'reference_number' => 'GC-7'],
            ],
        ])->assertStatus(200)
            ->assertJsonPath('data.total', 100)
            ->assertJsonPath('data.fully_settled', false);

        $sale = Sale::firstOrFail();
        $this->assertEquals(Sale::PAYMENT_MULTI, (int) $sale->payment_type);
        $this->assertEquals(30.0, (float) $sale->payments()->firstWhere('payment_type', Sale::PAYMENT_GIFT_CERT)->amount);
    }

    public function test_multi_tender_must_cover_the_amount_due(): void
    {
        [$orderId] = $this->openTwoLineOrder();

        $this->postJson("/api/v1/restaurant-orders/{$orderId}/settle", [
            'payments' => [
                ['payment_type' => Sale::PAYMENT_CASH, 'amount' => 50],
                ['payment_type' => Sale::PAYMENT_CARD, 'amount' => 50],
            ],
        ])->assertStatus(422);

        $this->assertEquals(0, Sale::count());
        $this->assertEquals(0, SalePayment::count());
    }

    public function test_change_cannot_come_from_non_cash_tenders(): void
    {
        [$orderId] = $this->openTwoLineOrder();

        $this->postJson("/api/v1/restaurant-orders/{$orderId}/settle", [
            'payments' => [
                ['payment_type' => Sale::PAYMENT_CARD, 'amount' => 100],
                ['payment_type' => Sale::PAYMENT_GIFT_CERT, 'amount' => 100],
            ],
        ])->assertStatus(422);

        $this->assertEquals(0, Sale::count());
    }

    public function test_credit_and_cheque_tenders_are_rejected(): void
    {
        [$orderId] = $this->openTwoLineOrder();

        foreach ([Sale::PAYMENT_CREDIT, Sale::PAYMENT_CHEQUE] as $forbidden) {
            $this->postJson("/api/v1/restaurant-orders/{$orderId}/settle", [
                'payments' => [
                    ['payment_type' => Sale::PAYMENT_CASH, 'amount' => 100],
                    ['payment_type' => $forbidden, 'amount' => 50],
                ],
            ])->assertStatus(422);
        }

        $this->assertEquals(0, Sale::count());
    }

    public function test_a_single_entry_payments_array_is_rejected(): void
    {
        [$orderId] = $this->openTwoLineOrder();

        $this->postJson("/api/v1/restaurant-orders/{$orderId}/settle", [
            'payments' => [
                ['payment_type' => Sale::PAYMENT_CASH, 'amount' => 150],
            ],
        ])->assertStatus(422);

        $this->assertEquals(0, Sale::count());
    }

    public function test_x_reading_folds_multi_tender_amounts_into_their_buckets(): void
    {
        // Multi-tender: 100 cash + 50 card.
        [$multiOrderId] = $this->openTwoLineOrder();
        $this->postJson("/api/v1/restaurant-orders/{$multiOrderId}/settle", [
            'payments' => [
                ['payment_type' => Sale::PAYMENT_CASH, 'amount' => 100],
                ['payment_type' => Sale::PAYMENT_CARD, 'amount' => 50],
            ],
        ])->assertStatus(200);

        // Plain single-tender cash sale for 150 on the same terminal.
        [$cashOrderId] = $this->openTwoLineOrder();
        $this->postJson("/api/v1/restaurant-orders/{$cashOrderId}/settle", [
            'payment_type' => Sale::PAYMENT_CASH,
            'cash' => 150,
        ])->assertStatus(200);

        $reading = $this->getJson('/api/v1/xreadings/apex/generate/'.$this->pos->id)
            ->assertStatus(200)
            ->json('data.reading.0');

        $this->assertEquals(250.0, (float) $reading['cash']); // 150 single + 100 portion
        $this->assertEquals(50.0, (float) $reading['card']);
        $this->assertEquals(2, (int) $reading['transactions']);
        $this->assertEquals(300.0, (float) $reading['net_sales']);
    }

    public function test_z_reading_annex_f_includes_multi_tender_portions(): void
    {
        [$orderId] = $this->openTwoLineOrder();
        $this->postJson("/api/v1/restaurant-orders/{$orderId}/settle", [
            'payments' => [
                ['payment_type' => Sale::PAYMENT_CARD, 'amount' => 90],
                ['payment_type' => Sale::PAYMENT_GIFT_CERT, 'amount' => 60],
            ],
        ])->assertStatus(200);

        $this->postJson('/api/v1/zreadings/save/'.$this->pos->id, [
            'pos_id' => $this->pos->id,
            'previous_accumulated_sales' => 0,
            'present_accumulated_sales' => 150,
        ])->assertStatus(200);

        $zreading = Zreading::latest('id')->firstOrFail();
        $this->assertEquals(150.0, (float) $zreading->gross_sales);
        $this->assertEquals(90.0, (float) $zreading->card);
        $this->assertEquals(60.0, (float) $zreading->gift_cert);
    }

    public function test_daily_aggregation_buckets_multi_tender_amounts(): void
    {
        [$orderId] = $this->openTwoLineOrder();
        $this->postJson("/api/v1/restaurant-orders/{$orderId}/settle", [
            'payments' => [
                ['payment_type' => Sale::PAYMENT_CASH, 'amount' => 100],
                ['payment_type' => Sale::PAYMENT_EWALLET, 'amount' => 50],
            ],
        ])->assertStatus(200);

        app(DailyAggregationService::class)
            ->aggregateDate(Carbon::today(config('app.timezone', 'Asia/Manila')));

        $row = DailyStoreMetric::firstOrFail();
        $this->assertEquals(100.0, (float) $row->cash_total);
        $this->assertEquals(50.0, (float) $row->ewallet_total);
        $this->assertEquals(150.0, (float) $row->gross_sales);
    }
}
