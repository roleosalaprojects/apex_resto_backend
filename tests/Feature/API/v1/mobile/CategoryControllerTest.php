<?php

namespace Tests\Feature\API\v1\mobile;

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
        Category::factory()->count(3)->create(['status' => true, 'user_id' => $this->user->user_id]);

        Passport::actingAs($this->user);

        $response = $this->getJson('/api/v1/mobile/categories');

        $response->assertStatus(200);
    }

    public function test_can_create_category(): void
    {
        Passport::actingAs($this->user);

        $response = $this->postJson('/api/v1/mobile/categories', [
            'name' => 'New Category',
        ]);

        $response->assertStatus(201);
        $response->assertJson(['success' => true]);
    }

    public function test_can_update_category(): void
    {
        $category = Category::factory()->create(['status' => true, 'user_id' => $this->user->user_id]);

        Passport::actingAs($this->user);

        $response = $this->putJson("/api/v1/mobile/categories/{$category->id}", [
            'name' => 'Updated Category',
        ]);

        $response->assertStatus(200);
    }

    public function test_can_delete_category(): void
    {
        $category = Category::factory()->create(['status' => true, 'user_id' => $this->user->user_id]);

        Passport::actingAs($this->user);

        $response = $this->deleteJson("/api/v1/mobile/categories/{$category->id}");

        $response->assertStatus(200);
    }

    public function test_unauthenticated_user_cannot_access_categories(): void
    {
        $response = $this->getJson('/api/v1/mobile/categories');

        $response->assertStatus(401);
    }
}
