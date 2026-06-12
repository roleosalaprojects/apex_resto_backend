<?php

namespace Tests\Feature\Admin;

use App\Models\Ecommerce\ShopAnnouncement;
use App\Models\Employees\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ShopAnnouncementTest extends TestCase
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

    public function test_index_page_loads(): void
    {
        $response = $this->actingAs($this->user)->get('/admin/shop-announcements');

        $response->assertStatus(200);
        $response->assertSee('Shop Announcements');
    }

    public function test_create_page_loads(): void
    {
        $response = $this->actingAs($this->user)->get('/admin/shop-announcements/create');

        $response->assertStatus(200);
        $response->assertSee('Create');
    }

    public function test_store_announcement_with_image(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->user)->post('/admin/shop-announcements', [
            'title' => 'Summer Sale',
            'description' => 'Big discounts on all products',
            'media' => UploadedFile::fake()->image('banner.jpg', 800, 400),
            'media_type' => 'image',
            'position' => 'hero',
            'display_order' => 1,
            'is_active' => true,
        ]);

        $response->assertRedirect('/admin/shop-announcements');
        $this->assertDatabaseHas('shop_announcements', [
            'title' => 'Summer Sale',
            'description' => 'Big discounts on all products',
            'media_type' => 'image',
            'position' => 'hero',
            'is_active' => true,
        ]);
    }

    public function test_store_announcement_with_video(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->user)->post('/admin/shop-announcements', [
            'title' => 'Video Promo',
            'media' => UploadedFile::fake()->create('promo.mp4', 1000, 'video/mp4'),
            'media_type' => 'video',
            'position' => 'hero',
            'is_active' => true,
        ]);

        $response->assertRedirect('/admin/shop-announcements');
        $this->assertDatabaseHas('shop_announcements', [
            'title' => 'Video Promo',
            'media_type' => 'video',
        ]);
    }

    public function test_store_requires_title(): void
    {
        $response = $this->actingAs($this->user)->post('/admin/shop-announcements', [
            'media' => UploadedFile::fake()->image('banner.jpg'),
            'media_type' => 'image',
            'position' => 'hero',
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors(['title']);
    }

    public function test_store_requires_media(): void
    {
        $response = $this->actingAs($this->user)->post('/admin/shop-announcements', [
            'title' => 'Test Announcement',
            'media_type' => 'image',
            'position' => 'hero',
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors(['media']);
    }

    public function test_edit_page_loads(): void
    {
        $announcement = ShopAnnouncement::factory()->create();

        $response = $this->actingAs($this->user)->get('/admin/shop-announcements/'.$announcement->id.'/edit');

        $response->assertStatus(200);
        $response->assertSee($announcement->title);
    }

    public function test_update_announcement(): void
    {
        $announcement = ShopAnnouncement::factory()->create();

        $response = $this->actingAs($this->user)->put('/admin/shop-announcements/'.$announcement->id, [
            'title' => 'Updated Title',
            'description' => 'Updated description',
            'media_type' => 'image',
            'old_media' => $announcement->media_path,
            'position' => 'banner',
            'display_order' => 5,
            'is_active' => false,
        ]);

        $response->assertRedirect('/admin/shop-announcements');
        $this->assertDatabaseHas('shop_announcements', [
            'id' => $announcement->id,
            'title' => 'Updated Title',
            'description' => 'Updated description',
            'position' => 'banner',
            'is_active' => false,
        ]);
    }

    public function test_delete_announcement(): void
    {
        $announcement = ShopAnnouncement::factory()->create();

        $response = $this->actingAs($this->user)->delete('/admin/shop-announcements/'.$announcement->id);

        $response->assertJson(['success' => true]);
        $this->assertSoftDeleted('shop_announcements', [
            'id' => $announcement->id,
        ]);
    }

    public function test_table_endpoint_returns_data(): void
    {
        ShopAnnouncement::factory()->count(3)->create();

        $response = $this->actingAs($this->user)->get('/admin/shop-announcements/table');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => ['title', 'preview', 'type_badge', 'position_badge', 'status_badge', 'actions'],
            ],
        ]);
    }

    public function test_show_page_loads(): void
    {
        $announcement = ShopAnnouncement::factory()->create();

        $response = $this->actingAs($this->user)->get('/admin/shop-announcements/'.$announcement->id);

        $response->assertStatus(200);
        $response->assertSee($announcement->title);
    }

    public function test_announcement_with_scheduling(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->user)->post('/admin/shop-announcements', [
            'title' => 'Scheduled Sale',
            'media' => UploadedFile::fake()->image('banner.jpg'),
            'media_type' => 'image',
            'position' => 'hero',
            'is_active' => true,
            'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'ends_at' => now()->addWeek()->format('Y-m-d H:i:s'),
        ]);

        $response->assertRedirect('/admin/shop-announcements');
        $this->assertDatabaseHas('shop_announcements', [
            'title' => 'Scheduled Sale',
        ]);

        $announcement = ShopAnnouncement::where('title', 'Scheduled Sale')->first();
        $this->assertNotNull($announcement->starts_at);
        $this->assertNotNull($announcement->ends_at);
    }

    public function test_unauthenticated_user_cannot_access(): void
    {
        $response = $this->get('/admin/shop-announcements');

        $response->assertRedirect('/admin/login');
    }
}
