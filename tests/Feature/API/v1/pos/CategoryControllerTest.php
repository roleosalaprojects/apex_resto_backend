<?php

namespace Tests\Feature\API\v1\pos;

use App\Models\Employees\Role;
use App\Models\Products\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
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
        ]);
    }

    public function test_can_list_categories(): void
    {
        Category::factory()->count(3)->create(['status' => true]);
        Category::factory()->inactive()->create();

        Passport::actingAs($this->user);

        $response = $this->getJson('/api/v1/categories');

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $response->assertJsonCount(3, 'data');
    }

    public function test_can_create_category(): void
    {
        Passport::actingAs($this->user);

        $response = $this->postJson('/api/v1/categories', [
            'name' => 'Test Category',
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
        ]);

        $this->assertDatabaseHas('categories', [
            'name' => 'Test Category',
            'status' => true,
        ]);
    }

    public function test_can_show_category(): void
    {
        $category = Category::factory()->create(['status' => true]);

        Passport::actingAs($this->user);

        $response = $this->getJson("/api/v1/categories/{$category->id}");

        $response->assertStatus(200);
    }

    public function test_can_update_category(): void
    {
        $category = Category::factory()->create(['status' => true]);

        Passport::actingAs($this->user);

        $response = $this->putJson("/api/v1/categories/{$category->id}", [
            'name' => 'Updated Category',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Updated Category',
        ]);
    }

    public function test_can_delete_category(): void
    {
        $category = Category::factory()->create(['status' => true]);

        Passport::actingAs($this->user);

        $response = $this->deleteJson("/api/v1/categories/{$category->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'status' => false,
        ]);
    }

    public function test_cannot_delete_already_deleted_category(): void
    {
        $category = Category::factory()->inactive()->create();

        Passport::actingAs($this->user);

        $response = $this->deleteJson("/api/v1/categories/{$category->id}");

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_access_categories(): void
    {
        $response = $this->getJson('/api/v1/categories');

        $response->assertStatus(401);
    }
}
