<?php

namespace Tests\Feature\API\v1\openclaw;

use App\Models\Accounting\PosLog;
use App\Models\ApiToken;
use App\Models\Employees\Role;
use App\Models\Settings\Store;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenclawCashOutsTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $otherOwner;

    protected User $cashier;

    protected Store $store;

    protected string $readToken;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->admin()->create();

        $this->owner = User::factory()->create(['role_id' => $role->id]);
        $this->owner->forceFill(['user_id' => $this->owner->id])->save();

        $this->otherOwner = User::factory()->create(['role_id' => $role->id]);
        $this->otherOwner->forceFill(['user_id' => $this->otherOwner->id])->save();

        $this->cashier = User::factory()->create([
            'role_id' => $role->id,
            'user_id' => $this->owner->user_id,
            'name' => 'Cashier One',
        ]);

        $this->store = Store::factory()->create(['user_id' => $this->owner->user_id]);

        $this->readToken = $this->mintToken(['openclaw:read']);
    }

    private function mintToken(array $abilities): string
    {
        $plain = ApiToken::generatePlainToken();
        ApiToken::create([
            'user_id' => $this->owner->user_id,
            'name' => 'Test',
            'token' => ApiToken::hashToken($plain),
            'abilities' => $abilities,
        ]);

        return $plain;
    }

    private function makeCashOut(float $amount, string $reason, ?Carbon $when = null): PosLog
    {
        $when = $when ?? Carbon::now();
        $log = PosLog::create([
            'cash_in' => null,
            'rendered' => null,
            'cash_out' => $amount,
            'type' => 12,
            'reason' => $reason,
            'so_id' => null,
            'pos_id' => 1,
            'store_id' => $this->store->id,
            // pos_logs.user_id is the CASHIER, per the existing convention
            // (the openclaw query joins via this).
            'user_id' => $this->cashier->id,
        ]);

        // created_at / updated_at aren't in $fillable on PosLog so the
        // initial create gets auto-populated with now(); rewrite them
        // explicitly so date-window tests can pin records in time.
        $log->forceFill(['created_at' => $when, 'updated_at' => $when])->save();

        return $log;
    }

    private function makeVoidOf(PosLog $cashOut, ?Carbon $when = null): PosLog
    {
        $when = $when ?? Carbon::now();
        $log = PosLog::create([
            'cash_in' => null,
            'cash_out' => $cashOut->cash_out,
            'type' => 13,
            'reason' => 'Voided cash-out',
            'so_id' => $cashOut->id,
            'pos_id' => $cashOut->pos_id,
            'store_id' => $cashOut->store_id,
            'user_id' => $cashOut->user_id,
        ]);
        $log->forceFill(['created_at' => $when, 'updated_at' => $when])->save();

        return $log;
    }

    public function test_index_lists_active_cash_outs_with_employee_and_store(): void
    {
        // Place all cash-outs at noon-today so the default 30-day window catches them.
        $a = $this->makeCashOut(500, 'Cash for petty supplies', Carbon::today(config('app.timezone'))->setTime(12, 0));
        $b = $this->makeCashOut(1200, 'Lunch run', Carbon::today(config('app.timezone'))->setTime(13, 0));

        $response = $this->withHeader('Authorization', "Bearer {$this->readToken}")
            ->getJson('/api/v1/openclaw/cash-outs');

        $response->assertStatus(200)
            ->assertJsonPath('data.count', 2);

        $this->assertEqualsWithDelta(1700.0, $response->json('data.totals.amount'), 0.001);

        $rows = collect($response->json('data.cash_outs'))->keyBy('id');
        $this->assertSame('Cash for petty supplies', $rows[$a->id]['reason']);
        $this->assertSame('Cashier One', $rows[$a->id]['employee_name']);
        $this->assertSame($this->store->id, $rows[$a->id]['store_id']);
        $this->assertEqualsWithDelta(500.0, $rows[$a->id]['amount'], 0.001);
        $this->assertEqualsWithDelta(1200.0, $rows[$b->id]['amount'], 0.001);
    }

    public function test_index_excludes_voided_cash_outs(): void
    {
        $today = Carbon::today(config('app.timezone'))->setTime(12, 0);
        $active = $this->makeCashOut(500, 'Active', $today);
        $voided = $this->makeCashOut(750, 'About to be voided', $today);
        $this->makeVoidOf($voided, $today);

        $response = $this->withHeader('Authorization', "Bearer {$this->readToken}")
            ->getJson('/api/v1/openclaw/cash-outs');

        $response->assertStatus(200)->assertJsonPath('data.count', 1);
        $ids = collect($response->json('data.cash_outs'))->pluck('id')->all();
        $this->assertContains($active->id, $ids);
        $this->assertNotContains($voided->id, $ids);
    }

    public function test_index_filters_by_store_and_pos(): void
    {
        $today = Carbon::today(config('app.timezone'))->setTime(12, 0);
        $store2 = Store::factory()->create(['user_id' => $this->owner->user_id]);

        $a = $this->makeCashOut(100, 'A', $today);
        $b = $this->makeCashOut(200, 'B', $today);
        $b->update(['store_id' => $store2->id, 'pos_id' => 2]);

        $auth = ['Authorization' => "Bearer {$this->readToken}"];

        $byStore = $this->withHeaders($auth)->getJson("/api/v1/openclaw/cash-outs?store_id={$store2->id}");
        $byStore->assertStatus(200)->assertJsonPath('data.count', 1);
        $this->assertSame($b->id, $byStore->json('data.cash_outs.0.id'));

        $byPos = $this->withHeaders($auth)->getJson('/api/v1/openclaw/cash-outs?pos_id=2');
        $byPos->assertStatus(200)->assertJsonPath('data.count', 1);
        $this->assertSame($b->id, $byPos->json('data.cash_outs.0.id'));
    }

    public function test_index_excludes_other_tenant_cash_outs(): void
    {
        $today = Carbon::today(config('app.timezone'))->setTime(12, 0);
        $foreignCashier = User::factory()->create([
            'role_id' => $this->cashier->role_id,
            'user_id' => $this->otherOwner->user_id,
        ]);

        $this->makeCashOut(500, 'Mine', $today);
        $foreignLog = PosLog::create([
            'cash_out' => 9999, 'type' => 12, 'reason' => 'Theirs',
            'pos_id' => 1, 'store_id' => null,
            'user_id' => $foreignCashier->id,
        ]);
        $foreignLog->forceFill(['created_at' => $today, 'updated_at' => $today])->save();

        $response = $this->withHeader('Authorization', "Bearer {$this->readToken}")
            ->getJson('/api/v1/openclaw/cash-outs');

        $response->assertStatus(200)->assertJsonPath('data.count', 1);
        $reasons = collect($response->json('data.cash_outs'))->pluck('reason')->all();
        $this->assertContains('Mine', $reasons);
        $this->assertNotContains('Theirs', $reasons);
    }

    public function test_index_respects_date_window(): void
    {
        // Default window is the last 30 days. Make one inside, one outside.
        $today = Carbon::today(config('app.timezone'))->setTime(12, 0);
        $recent = $this->makeCashOut(100, 'Recent', $today);
        $this->makeCashOut(999, 'Old', $today->copy()->subDays(60));

        $response = $this->withHeader('Authorization', "Bearer {$this->readToken}")
            ->getJson('/api/v1/openclaw/cash-outs');

        $response->assertStatus(200)->assertJsonPath('data.count', 1);
        $this->assertSame($recent->id, $response->json('data.cash_outs.0.id'));
    }

    public function test_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/v1/openclaw/cash-outs')->assertStatus(401);
    }
}
