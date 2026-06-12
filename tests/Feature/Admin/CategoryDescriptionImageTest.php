<?php

namespace Tests\Feature\Admin;

use App\Models\Employees\Role;
use App\Models\Products\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CategoryDescriptionImageTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->admin()->create();
        $this->user = User::factory()->create([
            'role_id' => $role->id,
            'user_id' => 1,
        ]);
    }

    public function test_store_category_with_description(): void
    {
        $response = $this->actingAs($this->user)->post('/admin/categories', [
            'name' => 'Fruits',
            'description' => 'Fresh fruits and vegetables',
        ]);

        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('categories', [
            'name' => 'Fruits',
            'description' => 'Fresh fruits and vegetables',
        ]);
    }

    public function test_store_category_with_image(): void
    {
        $response = $this->actingAs($this->user)->post('/admin/categories', [
            'name' => 'Beverages',
            'image' => UploadedFile::fake()->image('beverages.jpg', 200, 200),
        ]);

        $response->assertJson(['success' => true]);

        $category = Category::where('name', 'Beverages')->first();
        $this->assertNotNull($category->image);
        $this->assertStringContainsString('img/categories/', $category->image);
    }

    public function test_store_category_without_description_and_image(): void
    {
        $response = $this->actingAs($this->user)->post('/admin/categories', [
            'name' => 'Snacks',
        ]);

        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('categories', [
            'name' => 'Snacks',
            'description' => null,
            'image' => null,
        ]);
    }

    public function test_update_category_with_description(): void
    {
        $category = Category::factory()->create(['user_id' => 1]);

        $response = $this->actingAs($this->user)->put('/admin/categories/'.$category->id, [
            'name' => $category->name,
            'description' => 'Updated description',
        ]);

        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'description' => 'Updated description',
        ]);
    }

    public function test_update_category_with_image(): void
    {
        $category = Category::factory()->create(['user_id' => 1]);

        $response = $this->actingAs($this->user)->put('/admin/categories/'.$category->id, [
            'name' => $category->name,
            'image' => UploadedFile::fake()->image('new-image.png', 300, 300),
        ]);

        $response->assertJson(['success' => true]);

        $category->refresh();
        $this->assertNotNull($category->image);
    }

    public function test_get_category_returns_description_and_image(): void
    {
        $category = Category::factory()->create([
            'user_id' => 1,
            'description' => 'Test description',
            'image' => 'img/categories/test.jpg',
        ]);

        $response = $this->actingAs($this->user)->get('/admin/categories/get/'.$category->id);

        $response->assertJson([
            'name' => $category->name,
            'description' => 'Test description',
        ]);
        $response->assertJsonStructure(['name', 'description', 'image']);
    }

    public function test_description_max_length_validation(): void
    {
        $response = $this->actingAs($this->user)->postJson('/admin/categories', [
            'name' => 'TestCategory',
            'description' => str_repeat('a', 1001),
        ]);

        $response->assertJsonValidationErrors(['description']);
    }

    public function test_image_must_be_valid_image_file(): void
    {
        $response = $this->actingAs($this->user)->postJson('/admin/categories', [
            'name' => 'TestCategory',
            'image' => UploadedFile::fake()->create('document.pdf', 100),
        ]);

        $response->assertJsonValidationErrors(['image']);
    }

    public function test_category_shows_on_ecommerce_page(): void
    {
        $category = Category::factory()->create([
            'user_id' => 1,
            'name' => 'Fresh Fruits',
        ]);

        $this->get('/shop')
            ->assertStatus(200)
            ->assertSee('Fresh Fruits');
    }

    public function test_store_category_with_icon(): void
    {
        $response = $this->actingAs($this->user)->post('/admin/categories', [
            'name' => 'Meats',
            'icon' => '🥩',
        ]);

        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('categories', [
            'name' => 'Meats',
            'icon' => '🥩',
        ]);
    }

    public function test_update_category_with_icon(): void
    {
        $category = Category::factory()->create(['user_id' => 1]);

        $response = $this->actingAs($this->user)->put('/admin/categories/'.$category->id, [
            'name' => $category->name,
            'icon' => '🛒',
        ]);

        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'icon' => '🛒',
        ]);
    }

    public function test_get_category_returns_icon(): void
    {
        $category = Category::factory()->create([
            'user_id' => 1,
            'icon' => '🥬',
        ]);

        $response = $this->actingAs($this->user)->get('/admin/categories/get/'.$category->id);

        $response->assertJson([
            'name' => $category->name,
            'icon' => '🥬',
        ]);
    }

    public function test_icon_max_length_validation(): void
    {
        $response = $this->actingAs($this->user)->postJson('/admin/categories', [
            'name' => 'TestCategory',
            'icon' => str_repeat('a', 101),
        ]);

        $response->assertJsonValidationErrors(['icon']);
    }

    public function test_category_icon_shows_on_ecommerce_page(): void
    {
        $category = Category::factory()->create([
            'user_id' => 1,
            'icon' => '🍎',
        ]);

        $this->get('/shop')
            ->assertStatus(200)
            ->assertSee('🍎');
    }
}
