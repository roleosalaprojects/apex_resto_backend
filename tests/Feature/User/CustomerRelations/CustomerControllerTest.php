<?php

namespace Tests\Feature\User\CustomerRelations;

use App\Models\CustomerRelations\Customer;
use App\Models\Employees\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CustomerControllerTest extends TestCase
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
            'status' => true,
        ]);
    }

    public function test_can_view_customers_index(): void
    {
        $response = $this->actingAs($this->user)->get('/admin/customers');

        $response->assertOk();
    }

    public function test_can_view_create_customer_form(): void
    {
        $response = $this->actingAs($this->user)->get('/admin/customers/create');

        $response->assertOk();
    }

    public function test_can_get_customers_table_data(): void
    {
        $response = $this->actingAs($this->user)
            ->get('/admin/customers/table');

        $response->assertStatus(200);
    }

    public function test_can_view_members_report(): void
    {
        $response = $this->actingAs($this->user)->get('/admin/customers/members');

        $response->assertOk();
    }

    public function test_can_view_non_members_report(): void
    {
        $response = $this->actingAs($this->user)->get('/admin/customers/nonMembers');

        $response->assertOk();
    }

    public function test_unauthenticated_user_cannot_access_customers(): void
    {
        $response = $this->get('/admin/customers');

        $response->assertRedirect('/admin/login');
    }

    public function test_admin_customer_image_upload_strips_attacker_controlled_extension(): void
    {
        Bus::fake();

        DB::table('receipts')->insert([
            'header' => 'Test',
            'footer' => 'Test',
            'vat_reg' => 0,
            'tin' => '000',
            'points' => 0,
            'user_id' => $this->user->user_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $jpegPath = tempnam(sys_get_temp_dir(), 'admin_avatar').'.jpg';
        $gd = imagecreatetruecolor(10, 10);
        imagejpeg($gd, $jpegPath);
        imagedestroy($gd);

        $file = new UploadedFile($jpegPath, 'evil.html', 'image/jpeg', null, true);
        $createdImagePath = null;

        try {
            $response = $this->actingAs($this->user)->post('/admin/customers', [
                'name' => 'Polyglot Test',
                'code' => 'POLY-'.strtoupper(uniqid()),
                'phone' => '0917-555-0000',
                'image' => $file,
            ]);

            $response->assertRedirect(route('customers.index'));

            $customer = Customer::where('name', 'Polyglot Test')->firstOrFail();
            $createdImagePath = $customer->image;

            $this->assertNotEmpty($customer->image);
            $this->assertStringEndsWith('.jpg', $customer->image, 'Saved image must use the content-derived extension.');
            $this->assertStringNotContainsString('.html', $customer->image);
            $this->assertFileExists(public_path($customer->image));
        } finally {
            if ($createdImagePath && is_file(public_path($createdImagePath))) {
                @unlink(public_path($createdImagePath));
            }
            if (is_file($jpegPath)) {
                @unlink($jpegPath);
            }
        }
    }

    public function test_admin_customer_image_upload_rejects_non_image(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->user)
            ->from('/admin/customers/create')
            ->post('/admin/customers', [
                'name' => 'PDF Test',
                'code' => 'PDF-'.strtoupper(uniqid()),
                'phone' => '0917-555-0000',
                'image' => $file,
            ]);

        $response->assertSessionHasErrors('image');
        $this->assertDatabaseMissing('customers', ['name' => 'PDF Test']);
    }
}
