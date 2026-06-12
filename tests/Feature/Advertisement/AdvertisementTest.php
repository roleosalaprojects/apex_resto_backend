<?php

namespace Tests\Feature\Advertisement;

use App\Models\Employees\Role;
use App\Models\Settings\Advertisement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AdvertisementTest extends TestCase
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

    public function test_can_access_advertisements_table_api(): void
    {
        Advertisement::factory()->count(3)->create();

        $response = $this->actingAs($this->user)->get('/admin/advertisements/table');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_can_create_image_advertisement(): void
    {
        $response = $this->actingAs($this->user)->post('/admin/advertisements', [
            'name' => 'Test Image Ad',
            'description' => 'Test description',
            'media_type' => 'image',
            'media' => UploadedFile::fake()->image('ad.jpg', 800, 600),
            'duration' => 15,
            'status' => 1,
            'display_order' => 1,
        ]);

        $response->assertRedirect(route('advertisements.index'));
        $this->assertDatabaseHas('advertisements', [
            'name' => 'Test Image Ad',
            'media_type' => 'image',
            'duration' => 15,
            'status' => true,
        ]);
    }

    public function test_can_create_video_advertisement(): void
    {
        $response = $this->actingAs($this->user)->post('/admin/advertisements', [
            'name' => 'Test Video Ad',
            'description' => 'Video ad description',
            'media_type' => 'video',
            'media' => UploadedFile::fake()->create('ad.mp4', 5000, 'video/mp4'),
            'duration' => 60,
            'status' => 1,
            'display_order' => 2,
        ]);

        $response->assertRedirect(route('advertisements.index'));
        $this->assertDatabaseHas('advertisements', [
            'name' => 'Test Video Ad',
            'media_type' => 'video',
            'duration' => 60,
        ]);
    }

    public function test_image_duration_must_not_exceed_60_seconds(): void
    {
        $response = $this->actingAs($this->user)->postJson('/admin/advertisements', [
            'name' => 'Test Ad',
            'media_type' => 'image',
            'media' => UploadedFile::fake()->image('ad.jpg'),
            'duration' => 120,
            'status' => 1,
        ]);

        $response->assertJsonValidationErrors(['duration']);
    }

    public function test_video_duration_can_be_up_to_300_seconds(): void
    {
        $response = $this->actingAs($this->user)->post('/admin/advertisements', [
            'name' => 'Long Video Ad',
            'media_type' => 'video',
            'media' => UploadedFile::fake()->create('video.mp4', 10000, 'video/mp4'),
            'duration' => 300,
            'status' => 1,
            'display_order' => 0,
        ]);

        $response->assertRedirect(route('advertisements.index'));
        $this->assertDatabaseHas('advertisements', [
            'name' => 'Long Video Ad',
            'duration' => 300,
            'description' => null,
        ]);
    }

    public function test_video_duration_must_not_exceed_300_seconds(): void
    {
        $response = $this->actingAs($this->user)->postJson('/admin/advertisements', [
            'name' => 'Test Ad',
            'media_type' => 'video',
            'media' => UploadedFile::fake()->create('ad.mp4', 5000, 'video/mp4'),
            'duration' => 400,
            'status' => 1,
        ]);

        $response->assertJsonValidationErrors(['duration']);
    }

    public function test_duration_must_be_at_least_5_seconds(): void
    {
        $response = $this->actingAs($this->user)->postJson('/admin/advertisements', [
            'name' => 'Test Ad',
            'media_type' => 'image',
            'media' => UploadedFile::fake()->image('ad.jpg'),
            'duration' => 2,
            'status' => 1,
        ]);

        $response->assertJsonValidationErrors(['duration']);
    }

    public function test_table_api_returns_new_fields(): void
    {
        $advertisement = Advertisement::factory()->create([
            'media_type' => 'video',
            'duration' => 120,
            'status' => true,
            'display_order' => 5,
        ]);

        $response = $this->actingAs($this->user)->get('/admin/advertisements/table');

        $response->assertStatus(200);
        $data = $response->json('data.0');
        $this->assertEquals('video', $data['media_type']);
        $this->assertEquals(120, $data['duration']);
        $this->assertEquals(true, $data['status']);
        $this->assertEquals(5, $data['display_order']);
    }

    public function test_can_update_advertisement(): void
    {
        $advertisement = Advertisement::factory()->create();

        $response = $this->actingAs($this->user)->put('/admin/advertisements/'.$advertisement->id, [
            'name' => 'Updated Name',
            'description' => 'Updated description',
            'media_type' => 'image',
            'old_media' => $advertisement->image,
            'duration' => 20,
            'status' => 0,
            'display_order' => 5,
        ]);

        $response->assertRedirect(route('advertisements.index'));
        $this->assertDatabaseHas('advertisements', [
            'id' => $advertisement->id,
            'name' => 'Updated Name',
            'status' => false,
        ]);
    }

    public function test_can_delete_advertisement(): void
    {
        $advertisement = Advertisement::factory()->create();

        $response = $this->actingAs($this->user)->delete('/admin/advertisements/'.$advertisement->id);

        $response->assertJson(['success' => true]);
        $this->assertSoftDeleted('advertisements', ['id' => $advertisement->id]);
    }

    public function test_api_returns_only_active_advertisements(): void
    {
        Advertisement::factory()->count(3)->create();
        Advertisement::factory()->inactive()->count(2)->create();

        $response = $this->getJson('/api/v1/advertisements');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_api_returns_advertisements_ordered_by_display_order(): void
    {
        Advertisement::factory()->create(['display_order' => 3, 'name' => 'Third']);
        Advertisement::factory()->create(['display_order' => 1, 'name' => 'First']);
        Advertisement::factory()->create(['display_order' => 2, 'name' => 'Second']);

        $response = $this->getJson('/api/v1/advertisements');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals('First', $data[0]['name']);
        $this->assertEquals('Second', $data[1]['name']);
        $this->assertEquals('Third', $data[2]['name']);
    }

    public function test_api_can_filter_by_media_type(): void
    {
        Advertisement::factory()->count(2)->create(['media_type' => 'image']);
        Advertisement::factory()->video()->count(3)->create();

        $response = $this->getJson('/api/v1/advertisements?media_type=video');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_api_response_includes_required_fields(): void
    {
        $advertisement = Advertisement::factory()->create();

        $response = $this->getJson('/api/v1/advertisements');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'description',
                    'media_url',
                    'media_type',
                    'duration',
                    'display_order',
                ],
            ],
            'count',
        ]);
    }
}
