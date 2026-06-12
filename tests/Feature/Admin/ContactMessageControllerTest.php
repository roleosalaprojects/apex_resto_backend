<?php

namespace Tests\Feature\Admin;

use App\Models\CustomerRelations\ContactMessage;
use App\Models\Employees\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactMessageControllerTest extends TestCase
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
        $response = $this->actingAs($this->user)->get('/admin/contact-messages');

        $response->assertStatus(200);
        $response->assertSee('Contact Messages');
    }

    public function test_show_page_loads_and_marks_as_read(): void
    {
        $message = ContactMessage::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'subject' => 'other',
            'message' => 'This is a test contact message.',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Agent',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user)->get("/admin/contact-messages/{$message->id}");

        $response->assertStatus(200);
        $response->assertSee('John Doe');
        $response->assertSee('john@example.com');

        $message->refresh();
        $this->assertEquals('read', $message->status);
        $this->assertNotNull($message->read_at);
    }

    public function test_show_does_not_change_read_status_if_already_read(): void
    {
        $message = ContactMessage::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'subject' => 'web-development',
            'message' => 'Already read message.',
            'ip_address' => '127.0.0.1',
            'status' => 'read',
            'read_at' => now()->subHour(),
        ]);

        $originalReadAt = $message->read_at->toDateTimeString();

        $this->actingAs($this->user)->get("/admin/contact-messages/{$message->id}");

        $message->refresh();
        $this->assertEquals('read', $message->status);
        $this->assertEquals($originalReadAt, $message->read_at->toDateTimeString());
    }

    public function test_mark_as_replied(): void
    {
        $message = ContactMessage::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'subject' => 'tech-support',
            'message' => 'Need help with something.',
            'ip_address' => '127.0.0.1',
            'status' => 'read',
        ]);

        $response = $this->actingAs($this->user)
            ->post("/admin/contact-messages/{$message->id}/mark-replied");

        $response->assertJson(['success' => true]);

        $message->refresh();
        $this->assertEquals('replied', $message->status);
    }

    public function test_archive_message(): void
    {
        $message = ContactMessage::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'subject' => 'other',
            'message' => 'Archive this message.',
            'ip_address' => '127.0.0.1',
            'status' => 'read',
        ]);

        $response = $this->actingAs($this->user)
            ->post("/admin/contact-messages/{$message->id}/archive");

        $response->assertJson(['success' => true]);

        $message->refresh();
        $this->assertEquals('archived', $message->status);
    }

    public function test_delete_message(): void
    {
        $message = ContactMessage::create([
            'name' => 'Delete Me',
            'email' => 'delete@example.com',
            'subject' => 'other',
            'message' => 'This should be deleted.',
            'ip_address' => '127.0.0.1',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user)
            ->delete("/admin/contact-messages/{$message->id}");

        $response->assertJson(['success' => true]);
        $this->assertDatabaseMissing('contact_messages', ['id' => $message->id]);
    }

    public function test_table_endpoint_returns_data(): void
    {
        ContactMessage::create([
            'name' => 'Table User',
            'email' => 'table@example.com',
            'subject' => 'pos-system',
            'message' => 'Testing the datatable endpoint.',
            'ip_address' => '127.0.0.1',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/admin/contact-messages/table');

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'Table User']);
    }

    public function test_unauthenticated_user_is_redirected(): void
    {
        $response = $this->get('/admin/contact-messages');

        $response->assertRedirect();
    }
}
