<?php

namespace Tests\Feature\User\Products;

use App\Jobs\ProcessBulkPriceUpdateJob;
use App\Jobs\ProcessCsvImportJob;
use App\Models\BulkOperationLog;
use App\Models\Employees\Employee;
use App\Models\Employees\Role;
use App\Models\Products\Category;
use App\Models\Products\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BulkOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Role $role;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->role = Role::factory()->admin()->create();
        $this->user = User::factory()->create([
            'role_id' => $this->role->id,
            'user_id' => 1,
            'status' => true,
        ]);
        Employee::create([
            'user_id' => $this->user->id,
            'phone' => '123456789',
            'address' => 'Test Address',
            'status' => true,
            'image' => null,
        ]);
        $this->category = Category::factory()->create([
            'status' => true,
            'user_id' => $this->user->user_id,
        ]);
    }

    public function test_can_view_bulk_edit_page(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('products.bulk-edit'));

        $response->assertOk()
            ->assertViewIs('admin.products.items.bulk_edit');
    }

    public function test_unauthorized_user_cannot_view_bulk_edit_page(): void
    {
        $restrictedRole = Role::factory()->create([
            'itms' => true,
            'itms_update' => false,
        ]);
        $restrictedUser = User::factory()->create([
            'role_id' => $restrictedRole->id,
            'user_id' => 1,
            'status' => true,
        ]);

        $response = $this->actingAs($restrictedUser)
            ->get(route('products.bulk-edit'));

        $response->assertRedirect('/home');
    }

    public function test_can_bulk_update_prices_with_fixed_amount(): void
    {
        $items = Item::factory()->count(3)->create([
            'status' => true,
            'category_id' => $this->category->id,
            'user_id' => $this->user->user_id,
            'price' => 100.00,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson(route('products.bulk-update-prices'), [
                'item_ids' => $items->pluck('id')->toArray(),
                'update_type' => 'fixed',
                'field' => 'price',
                'value' => 10.00,
                'direction' => 'increase',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'updated' => 3,
        ]);

        foreach ($items as $item) {
            $this->assertDatabaseHas('items', [
                'id' => $item->id,
                'price' => 110.00,
            ]);
        }
    }

    public function test_can_bulk_update_prices_with_percentage(): void
    {
        $item = Item::factory()->create([
            'status' => true,
            'category_id' => $this->category->id,
            'user_id' => $this->user->user_id,
            'price' => 100.00,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson(route('products.bulk-update-prices'), [
                'item_ids' => [$item->id],
                'update_type' => 'percentage',
                'field' => 'price',
                'value' => 20,
                'direction' => 'increase',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'price' => 120.00,
        ]);
    }

    public function test_can_bulk_decrease_prices(): void
    {
        $item = Item::factory()->create([
            'status' => true,
            'category_id' => $this->category->id,
            'user_id' => $this->user->user_id,
            'price' => 100.00,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson(route('products.bulk-update-prices'), [
                'item_ids' => [$item->id],
                'update_type' => 'fixed',
                'field' => 'price',
                'value' => 30.00,
                'direction' => 'decrease',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'price' => 70.00,
        ]);
    }

    public function test_price_cannot_go_below_zero(): void
    {
        $item = Item::factory()->create([
            'status' => true,
            'category_id' => $this->category->id,
            'user_id' => $this->user->user_id,
            'price' => 50.00,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson(route('products.bulk-update-prices'), [
                'item_ids' => [$item->id],
                'update_type' => 'fixed',
                'field' => 'price',
                'value' => 100.00,
                'direction' => 'decrease',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'price' => 0.00,
        ]);
    }

    public function test_bulk_price_update_creates_price_history(): void
    {
        $item = Item::factory()->create([
            'status' => true,
            'category_id' => $this->category->id,
            'user_id' => $this->user->user_id,
            'price' => 100.00,
        ]);

        $this->actingAs($this->user)
            ->postJson(route('products.bulk-update-prices'), [
                'item_ids' => [$item->id],
                'update_type' => 'fixed',
                'field' => 'price',
                'value' => 10.00,
                'direction' => 'increase',
            ]);

        $this->assertDatabaseHas('price_histories', [
            'item_id' => $item->id,
            'old_price' => 100.00,
            'new_price' => 110.00,
            'change_reason' => 'bulk',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_bulk_price_update_uses_queue_for_large_batches(): void
    {
        Queue::fake();

        $items = Item::factory()->count(51)->create([
            'status' => true,
            'category_id' => $this->category->id,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson(route('products.bulk-update-prices'), [
                'item_ids' => $items->pluck('id')->toArray(),
                'update_type' => 'fixed',
                'field' => 'price',
                'value' => 10.00,
                'direction' => 'increase',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'async' => true,
        ]);

        Queue::assertPushed(ProcessBulkPriceUpdateJob::class);
    }

    public function test_can_bulk_update_category(): void
    {
        $newCategory = Category::factory()->create([
            'status' => true,
            'user_id' => $this->user->user_id,
        ]);

        $items = Item::factory()->count(3)->create([
            'status' => true,
            'category_id' => $this->category->id,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson(route('products.bulk-update-category'), [
                'item_ids' => $items->pluck('id')->toArray(),
                'category_id' => $newCategory->id,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'updated' => 3,
        ]);

        foreach ($items as $item) {
            $this->assertDatabaseHas('items', [
                'id' => $item->id,
                'category_id' => $newCategory->id,
            ]);
        }
    }

    public function test_bulk_update_prices_validation_requires_items(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('products.bulk-update-prices'), [
                'item_ids' => [],
                'update_type' => 'fixed',
                'field' => 'price',
                'value' => 10.00,
                'direction' => 'increase',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['item_ids']);
    }

    public function test_bulk_update_prices_validation_requires_valid_field(): void
    {
        $item = Item::factory()->create([
            'status' => true,
            'category_id' => $this->category->id,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson(route('products.bulk-update-prices'), [
                'item_ids' => [$item->id],
                'update_type' => 'fixed',
                'field' => 'invalid_field',
                'value' => 10.00,
                'direction' => 'increase',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['field']);
    }

    public function test_can_export_csv_all_items(): void
    {
        Item::factory()->count(3)->create([
            'status' => true,
            'category_id' => $this->category->id,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('products.export-csv'));

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
    }

    public function test_can_export_csv_selected_items(): void
    {
        $items = Item::factory()->count(5)->create([
            'status' => true,
            'category_id' => $this->category->id,
            'user_id' => $this->user->user_id,
        ]);

        $selectedIds = $items->take(2)->pluck('id')->toArray();

        $response = $this->actingAs($this->user)
            ->get(route('products.export-csv', ['item_ids' => $selectedIds]));

        $response->assertStatus(200);
    }

    public function test_can_download_import_template(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('products.import-template'));

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $response->assertHeader('Content-Disposition', 'attachment; filename="items_import_template.csv"');
    }

    public function test_can_import_csv(): void
    {
        Queue::fake();
        Storage::fake('local');

        $csvContent = "barcode,name,category,supplier,cost,markup,price,vatable,type,status\n";
        $csvContent .= "1234567890123,TEST PRODUCT,Test Category,Test Supplier,100.00,20,120.00,1,PC,1\n";

        $file = UploadedFile::fake()->createWithContent('import.csv', $csvContent);

        $response = $this->actingAs($this->user)
            ->postJson(route('products.import-csv'), [
                'file' => $file,
                'update_existing' => false,
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        Queue::assertPushed(ProcessCsvImportJob::class);
    }

    public function test_import_csv_validation_requires_file(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('products.import-csv'), [
                'update_existing' => false,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file']);
    }

    public function test_can_get_bulk_operation_status(): void
    {
        $log = BulkOperationLog::factory()
            ->for($this->user)
            ->completed()
            ->create();

        $response = $this->actingAs($this->user)
            ->getJson(route('products.bulk-operation-status', $log));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'id',
            'type',
            'status',
            'total_records',
            'processed_records',
            'success_records',
            'failed_records',
            'progress_percent',
        ]);
    }

    public function test_bulk_operation_log_model_methods(): void
    {
        $log = BulkOperationLog::factory()
            ->for($this->user)
            ->pending()
            ->create(['total_records' => 10]);

        $this->assertEquals('pending', $log->status);
        $this->assertEquals(0, $log->progress_percent);

        $log->markAsProcessing();
        $log->refresh();
        $this->assertEquals('processing', $log->status);
        $this->assertNotNull($log->started_at);

        $log->incrementProcessed();
        $log->incrementSuccess();
        $log->refresh();
        $this->assertEquals(1, $log->processed_records);
        $this->assertEquals(1, $log->success_records);
        $this->assertEquals(10, $log->progress_percent);

        $log->addError(['message' => 'Test error']);
        $log->refresh();
        $this->assertCount(1, $log->errors);

        $log->markAsCompleted();
        $log->refresh();
        $this->assertEquals('completed', $log->status);
        $this->assertNotNull($log->completed_at);
    }

    public function test_process_bulk_price_update_job(): void
    {
        $items = Item::factory()->count(3)->create([
            'status' => true,
            'category_id' => $this->category->id,
            'user_id' => $this->user->user_id,
            'price' => 100.00,
        ]);

        $log = BulkOperationLog::factory()
            ->for($this->user)
            ->priceUpdate()
            ->pending()
            ->create(['total_records' => 3]);

        $job = new ProcessBulkPriceUpdateJob(
            $log,
            $items->pluck('id')->toArray(),
            'fixed',
            'price',
            10.00,
            'increase',
            $this->user->id
        );

        $job->handle();

        $log->refresh();
        $this->assertEquals('completed', $log->status);
        $this->assertEquals(3, $log->success_records);
        $this->assertEquals(0, $log->failed_records);

        foreach ($items as $item) {
            $item->refresh();
            $this->assertEquals(110.00, $item->price);
        }
    }

    public function test_process_csv_import_job(): void
    {
        Storage::fake('local');

        $csvContent = "barcode,name,category,supplier,cost,markup,price,vatable,type,status\n";
        $csvContent .= "9876543210987,IMPORTED PRODUCT,New Category,New Supplier,50.00,30,65.00,1,PC,1\n";

        Storage::put('imports/test.csv', $csvContent);

        $log = BulkOperationLog::factory()
            ->for($this->user)
            ->import()
            ->pending()
            ->create(['total_records' => 1]);

        $job = new ProcessCsvImportJob(
            $log,
            'imports/test.csv',
            false,
            $this->user->id
        );

        $job->handle();

        $log->refresh();
        $this->assertEquals('completed', $log->status);
        $this->assertEquals(1, $log->success_records);

        $this->assertDatabaseHas('items', [
            'barcode' => '9876543210987',
            'name' => 'IMPORTED PRODUCT',
        ]);

        $this->assertDatabaseHas('categories', [
            'name' => 'New Category',
        ]);

        $this->assertDatabaseHas('suppliers', [
            'name' => 'New Supplier',
        ]);
    }

    public function test_unauthenticated_user_cannot_access_bulk_operations(): void
    {
        $response = $this->postJson(route('products.bulk-update-prices'), []);
        $response->assertStatus(401);

        $response = $this->postJson(route('products.bulk-update-category'), []);
        $response->assertStatus(401);

        $response = $this->get(route('products.export-csv'));
        $response->assertRedirect('/admin/login');
    }
}
