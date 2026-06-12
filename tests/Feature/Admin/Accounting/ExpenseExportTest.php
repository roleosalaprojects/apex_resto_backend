<?php

namespace Tests\Feature\Admin\Accounting;

use App\Models\Accounting\Bank;
use App\Models\Accounting\Expense;
use App\Models\Accounting\ExpenseCategory;
use App\Models\Employees\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseExportTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Bank $bank;

    protected ExpenseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->admin()->create();
        $this->owner = User::factory()->create(['role_id' => $role->id]);
        $this->owner->forceFill(['user_id' => $this->owner->id])->save();

        $this->bank = Bank::create([
            'bank_name' => 'BDO',
            'account_name' => 'Leteres',
            'account_number' => '041078001670',
            'account_type' => Bank::TYPE_CHECKING,
            'opening_balance' => 1000000,
            'balance' => 800000,
        ]);

        $this->category = ExpenseCategory::create([
            'name' => 'Utilities',
            'description' => null,
            'status' => true,
            'created_by' => $this->owner->id,
        ]);
    }

    private function makeExpense(array $overrides = []): Expense
    {
        return Expense::create(array_merge([
            'reference_number' => Expense::generateReferenceNumber(),
            'expense_category_id' => $this->category->id,
            'store_id' => null,
            'bank_id' => $this->bank->id,
            'bank_transaction_id' => null,
            'payee' => 'Meralco',
            'amount' => 1250,
            'expense_date' => now()->toDateString(),
            'description' => 'May electric bill',
            'receipt_number' => 'MERALCO-001',
            'status' => Expense::STATUS_ACTIVE,
            'created_by' => $this->owner->id,
        ], $overrides));
    }

    public function test_expenses_export_returns_all_records_as_csv(): void
    {
        foreach (range(1, 30) as $i) {
            $this->makeExpense(['payee' => "Vendor {$i}"]);
        }

        $response = $this->actingAs($this->owner)
            ->get(route('expenses.export'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=utf-8');

        $lines = array_filter(explode("\n", trim($response->streamedContent())));
        $this->assertCount(31, $lines);
        $this->assertStringContainsString('Date,Payee,Category', $lines[0]);
    }

    public function test_expense_categories_export_returns_all_records_as_csv(): void
    {
        ExpenseCategory::create([
            'name' => 'Rent',
            'description' => 'Monthly rent',
            'status' => true,
            'created_by' => $this->owner->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->get(route('expense_categories.export'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=utf-8');

        $lines = array_filter(explode("\n", trim($response->streamedContent())));
        $this->assertCount(3, $lines);
        $this->assertStringContainsString('Name,Description', $lines[0]);
        $this->assertStringContainsString('Utilities', $response->streamedContent());
        $this->assertStringContainsString('Rent', $response->streamedContent());
    }

    public function test_unauthenticated_user_cannot_export_expenses(): void
    {
        $this->get(route('expenses.export'))->assertRedirect('/admin/login');
        $this->get(route('expense_categories.export'))->assertRedirect('/admin/login');
    }
}
