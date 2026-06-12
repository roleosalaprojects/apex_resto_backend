<?php

namespace Tests\Feature\User\Products;

use App\Models\Employees\Role;
use App\Models\Products\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryControllerTest extends TestCase
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

    public function test_can_view_categories_index(): void
    {
        $response = $this->actingAs($this->user)->get('/admin/categories');

        $response->assertOk();
    }

    public function test_can_store_category(): void
    {
        $response = $this->actingAs($this->user)
            ->post('/admin/categories', [
                'name' => 'Test Category',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('categories', [
            'name' => 'Test Category',
            'status' => true,
        ]);
    }

    public function test_can_view_category(): void
    {
        $category = Category::factory()->create([
            'status' => true,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->actingAs($this->user)->get("/admin/categories/{$category->id}");

        $response->assertOk();
    }

    public function test_can_update_category(): void
    {
        $category = Category::factory()->create([
            'status' => true,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->actingAs($this->user)
            ->put("/admin/categories/{$category->id}", [
                'name' => 'Updated Category',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Updated Category',
        ]);
    }

    public function test_can_delete_category(): void
    {
        $category = Category::factory()->create([
            'status' => true,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->actingAs($this->user)
            ->delete("/admin/categories/{$category->id}");

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'status' => false,
        ]);
    }

    public function test_can_get_categories_table_data(): void
    {
        Category::factory()->count(5)->create([
            'status' => true,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->actingAs($this->user)
            ->get('/admin/categories/table');

        $response->assertStatus(200);
    }

    public function test_can_select_categories(): void
    {
        Category::factory()->count(3)->create([
            'status' => true,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->actingAs($this->user)
            ->get('/admin/categories/select?term=');

        $response->assertStatus(200);
    }

    public function test_unauthenticated_user_cannot_access_categories(): void
    {
        $response = $this->get('/admin/categories');

        $response->assertRedirect('/admin/login');
    }
}
