<?php

namespace Tests\Feature\API\v1\openclaw;

use App\Models\Accounting\Bank;
use App\Models\Accounting\BankTransaction;
use App\Models\Accounting\Expense;
use App\Models\Accounting\ExpenseCategory;
use App\Models\ApiToken;
use App\Models\Employees\Role;
use App\Models\User;
use App\Services\ReceiptStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class OpenclawAttachmentsTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Bank $bank;

    protected ExpenseCategory $category;

    protected string $writeToken;

    protected string $readToken;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->admin()->create();
        $this->owner = User::factory()->create(['role_id' => $role->id]);
        $this->owner->forceFill(['user_id' => $this->owner->id])->save();

        $this->bank = Bank::create([
            'bank_name' => 'BPI',
            'account_name' => 'Main',
            'account_number' => '1',
            'account_type' => Bank::TYPE_CHECKING,
            'opening_balance' => 50000,
            'balance' => 50000,
        ]);

        $this->category = ExpenseCategory::create([
            'name' => 'Utilities', 'status' => 1, 'created_by' => $this->owner->id,
        ]);

        $this->writeToken = $this->mintToken([
            'openclaw:read',
            'openclaw:expenses:create',
            'openclaw:expenses:upload-receipt',
            'openclaw:banks:write',
        ]);
        $this->readToken = $this->mintToken(['openclaw:read']);
    }

    protected function tearDown(): void
    {
        // Tidy any test files we wrote to public/img/{receipts,bank-proofs}/.
        foreach ([ReceiptStorage::DIR_EXPENSE_RECEIPTS, ReceiptStorage::DIR_BANK_PROOFS] as $dir) {
            $abs = public_path($dir);
            if (is_dir($abs)) {
                foreach (glob($abs.'*') as $file) {
                    if (is_file($file)) {
                        @unlink($file);
                    }
                }
            }
        }

        parent::tearDown();
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

    private function makeExpense(): Expense
    {
        return Expense::create([
            'reference_number' => Expense::generateReferenceNumber(),
            'expense_category_id' => $this->category->id,
            'bank_id' => $this->bank->id,
            'payee' => 'Meralco',
            'amount' => 1250,
            'expense_date' => now()->toDateString(),
            'status' => Expense::STATUS_ACTIVE,
            'created_by' => $this->owner->id,
        ]);
    }

    private function makeBankTransaction(): BankTransaction
    {
        return BankTransaction::create([
            'reference_number' => BankTransaction::generateReferenceNumber(),
            'bank_id' => $this->bank->id,
            'type' => BankTransaction::TYPE_DEPOSIT,
            'amount' => 5000,
            'balance_before' => 50000,
            'balance_after' => 55000,
            'description' => 'Cash deposit',
            'transaction_date' => now()->toDateString(),
            'created_by' => $this->owner->id,
        ]);
    }

    // ---- Expense receipts ----

    public function test_uploads_a_receipt_to_an_expense(): void
    {
        $expense = $this->makeExpense();

        $response = $this->withHeader('Authorization', "Bearer {$this->writeToken}")
            ->postJson("/api/v1/openclaw/expenses/{$expense->id}/receipt", [
                'receipt' => UploadedFile::fake()->image('meralco.png', 800, 600),
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['expense' => ['receipt_photo_url']]]);

        $expense->refresh();
        $this->assertNotNull($expense->receipt_photo);
        $this->assertStringStartsWith('img/receipts/', $expense->receipt_photo);
        $this->assertFileExists(public_path($expense->receipt_photo));
    }

    public function test_replacing_a_receipt_deletes_the_old_file(): void
    {
        $expense = $this->makeExpense();
        $auth = ['Authorization' => "Bearer {$this->writeToken}"];

        $first = $this->withHeaders($auth)->postJson(
            "/api/v1/openclaw/expenses/{$expense->id}/receipt",
            ['receipt' => UploadedFile::fake()->image('first.png')]
        );
        $first->assertStatus(200);
        $oldPath = $expense->fresh()->receipt_photo;
        $this->assertFileExists(public_path($oldPath));

        $this->withHeaders($auth)->postJson(
            "/api/v1/openclaw/expenses/{$expense->id}/receipt",
            ['receipt' => UploadedFile::fake()->image('replaced.png')]
        )->assertStatus(200);

        $newPath = $expense->fresh()->receipt_photo;
        $this->assertNotSame($oldPath, $newPath);
        $this->assertFileDoesNotExist(public_path($oldPath));
        $this->assertFileExists(public_path($newPath));
    }

    public function test_delete_clears_receipt_photo(): void
    {
        $expense = $this->makeExpense();
        $auth = ['Authorization' => "Bearer {$this->writeToken}"];

        $this->withHeaders($auth)->postJson(
            "/api/v1/openclaw/expenses/{$expense->id}/receipt",
            ['receipt' => UploadedFile::fake()->image('a.png')]
        )->assertStatus(200);

        $path = $expense->fresh()->receipt_photo;
        $this->assertFileExists(public_path($path));

        $this->withHeaders($auth)
            ->deleteJson("/api/v1/openclaw/expenses/{$expense->id}/receipt")
            ->assertStatus(200);

        $this->assertNull($expense->fresh()->receipt_photo);
        $this->assertFileDoesNotExist(public_path($path));
    }

    public function test_upload_rejects_oversize_file(): void
    {
        $expense = $this->makeExpense();

        $this->withHeader('Authorization', "Bearer {$this->writeToken}")
            ->postJson("/api/v1/openclaw/expenses/{$expense->id}/receipt", [
                'receipt' => UploadedFile::fake()->create('big.jpg', ReceiptStorage::MAX_KILOBYTES + 1, 'image/jpeg'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('receipt');
    }

    public function test_upload_rejects_non_image_mime(): void
    {
        $expense = $this->makeExpense();

        $this->withHeader('Authorization', "Bearer {$this->writeToken}")
            ->postJson("/api/v1/openclaw/expenses/{$expense->id}/receipt", [
                'receipt' => UploadedFile::fake()->create('not-an-image.pdf', 100, 'application/pdf'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('receipt');
    }

    public function test_upload_requires_upload_receipt_ability(): void
    {
        $expense = $this->makeExpense();

        $this->withHeader('Authorization', "Bearer {$this->readToken}")
            ->postJson("/api/v1/openclaw/expenses/{$expense->id}/receipt", [
                'receipt' => UploadedFile::fake()->image('r.png'),
            ])
            ->assertStatus(403)
            ->assertJsonPath('message', 'This token is missing the required ability: openclaw:expenses:upload-receipt.');
    }

    // ---- Bank transaction proof ----

    public function test_uploads_a_proof_to_a_bank_transaction(): void
    {
        $tx = $this->makeBankTransaction();

        $response = $this->withHeader('Authorization', "Bearer {$this->writeToken}")
            ->postJson("/api/v1/openclaw/banks/transactions/{$tx->id}/proof", [
                'proof' => UploadedFile::fake()->image('slip.jpg', 1000, 800),
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['transaction' => ['proof_photo_url']]]);

        $tx->refresh();
        $this->assertNotNull($tx->proof_photo);
        $this->assertStringStartsWith('img/bank-proofs/', $tx->proof_photo);
        $this->assertFileExists(public_path($tx->proof_photo));
    }

    public function test_proof_endpoint_requires_banks_write_ability(): void
    {
        $tx = $this->makeBankTransaction();

        $this->withHeader('Authorization', "Bearer {$this->readToken}")
            ->postJson("/api/v1/openclaw/banks/transactions/{$tx->id}/proof", [
                'proof' => UploadedFile::fake()->image('slip.jpg'),
            ])
            ->assertStatus(403)
            ->assertJsonPath('message', 'This token is missing the required ability: openclaw:banks:write.');
    }

    public function test_proof_delete_removes_file(): void
    {
        $tx = $this->makeBankTransaction();
        $auth = ['Authorization' => "Bearer {$this->writeToken}"];

        $this->withHeaders($auth)->postJson(
            "/api/v1/openclaw/banks/transactions/{$tx->id}/proof",
            ['proof' => UploadedFile::fake()->image('slip.jpg')]
        )->assertStatus(200);

        $path = $tx->fresh()->proof_photo;
        $this->assertFileExists(public_path($path));

        $this->withHeaders($auth)
            ->deleteJson("/api/v1/openclaw/banks/transactions/{$tx->id}/proof")
            ->assertStatus(200);

        $this->assertNull($tx->fresh()->proof_photo);
        $this->assertFileDoesNotExist(public_path($path));
    }

    public function test_transactions_listing_includes_proof_photo_url(): void
    {
        $tx = $this->makeBankTransaction();
        $tx->forceFill(['proof_photo' => ReceiptStorage::DIR_BANK_PROOFS.'fixed.jpg'])->save();

        $response = $this->withHeader('Authorization', "Bearer {$this->readToken}")
            ->getJson('/api/v1/openclaw/banks/transactions');

        $response->assertStatus(200);
        $row = collect($response->json('data.transactions'))->firstWhere('id', $tx->id);
        $this->assertNotNull($row);
        $this->assertStringEndsWith('/img/bank-proofs/fixed.jpg', $row['proof_photo_url']);
    }
}
