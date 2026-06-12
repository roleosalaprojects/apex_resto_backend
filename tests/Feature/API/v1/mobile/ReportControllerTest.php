<?php

namespace Tests\Feature\API\v1\mobile;

use App\Models\Employees\Role;
use App\Models\Products\Category;
use App\Models\Products\Item;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Role $role;

    protected function setUp(): void
    {
        parent::setUp();

        $this->role = Role::factory()->admin()->create();
        $this->user = User::factory()->create([
            'role_id' => $this->role->id,
            'user_id' => 1,
        ]);
    }

    public function test_can_get_sales_summary(): void
    {
        Passport::actingAs($this->user);

        $startDate = Carbon::now()->subDays(7)->format('Y-m-d');
        $endDate = Carbon::now()->format('Y-m-d');

        $response = $this->getJson("/api/v1/mobile/sales-summary?startDate={$startDate}&endDate={$endDate}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'sales',
                'chart',
                'receipts',
            ],
        ]);
    }

    public function test_sales_summary_requires_date_range(): void
    {
        Passport::actingAs($this->user);

        $response = $this->getJson('/api/v1/mobile/sales-summary');

        $response->assertStatus(422);
    }

    public function test_can_get_items_data(): void
    {
        $category = Category::factory()->create(['status' => true, 'user_id' => $this->user->user_id]);

        Item::factory()->count(3)->create([
            'status' => true,
            'category_id' => $category->id,
            'user_id' => $this->user->user_id,
        ]);

        Passport::actingAs($this->user);

        $startDate = Carbon::now()->subDays(7)->format('Y-m-d');
        $endDate = Carbon::now()->format('Y-m-d');

        $response = $this->getJson("/api/v1/mobile/sales-by-item?startDate={$startDate}&endDate={$endDate}");

        $response->assertStatus(200);
    }

    public function test_unauthenticated_user_cannot_access_sales_summary(): void
    {
        $response = $this->getJson('/api/v1/mobile/sales-summary');

        $response->assertStatus(401);
    }
}
