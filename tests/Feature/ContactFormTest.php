<?php

namespace Tests\Feature;

use App\Mail\ContactFormNotification;
use App\Models\CustomerRelations\ContactMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_submit_contact_form_with_valid_data(): void
    {
        Mail::fake();

        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'subject' => 'web-development',
            'message' => 'I need help building a new website for my business.',
        ];

        $response = $this->postJson('/api/v1/contact', $data);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Thank you for your message! We will get back to you within 24-48 hours.',
            ]);

        $this->assertDatabaseHas('contact_messages', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'subject' => 'web-development',
            'status' => 'pending',
        ]);
    }

    public function test_contact_form_sends_email_notification(): void
    {
        Mail::fake();

        $data = [
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'subject' => 'mobile-app',
            'message' => 'I would like to discuss a mobile app project for my startup.',
        ];

        $response = $this->postJson('/api/v1/contact', $data);

        $response->assertStatus(201);

        Mail::assertQueued(ContactFormNotification::class, function ($mail) {
            return $mail->contactMessage->name === 'Jane Smith'
                && $mail->contactMessage->email === 'jane@example.com'
                && $mail->contactMessage->subject === 'mobile-app';
        });
    }

    public function test_contact_form_requires_name(): void
    {
        $data = [
            'email' => 'john@example.com',
            'subject' => 'web-development',
            'message' => 'Test message here.',
        ];

        $response = $this->postJson('/api/v1/contact', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_contact_form_requires_valid_email(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'invalid-email',
            'subject' => 'web-development',
            'message' => 'Test message here.',
        ];

        $response = $this->postJson('/api/v1/contact', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_contact_form_requires_valid_subject(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'subject' => 'invalid-subject',
            'message' => 'Test message here.',
        ];

        $response = $this->postJson('/api/v1/contact', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['subject']);
    }

    public function test_contact_form_requires_message_minimum_length(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'subject' => 'web-development',
            'message' => 'Short',
        ];

        $response = $this->postJson('/api/v1/contact', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['message']);
    }

    public function test_contact_form_accepts_all_valid_subjects(): void
    {
        Mail::fake();

        $validSubjects = [
            'web-development',
            'mobile-app',
            'pos-system',
            'api-development',
            'database-design',
            'tech-support',
            'other',
        ];

        foreach ($validSubjects as $subject) {
            $data = [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'subject' => $subject,
                'message' => 'This is a test message for the contact form.',
            ];

            $response = $this->postJson('/api/v1/contact', $data);

            $response->assertStatus(201);
        }

        $this->assertDatabaseCount('contact_messages', count($validSubjects));
        Mail::assertQueuedCount(count($validSubjects));
    }

    public function test_contact_form_stores_ip_and_user_agent(): void
    {
        Mail::fake();

        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'subject' => 'web-development',
            'message' => 'Test message for checking IP and user agent storage.',
        ];

        $response = $this->postJson('/api/v1/contact', $data, [
            'User-Agent' => 'Test Browser/1.0',
        ]);

        $response->assertStatus(201);

        $contactMessage = ContactMessage::first();
        $this->assertNotNull($contactMessage->ip_address);
        $this->assertNotNull($contactMessage->user_agent);
    }

    public function test_email_has_correct_reply_to_address(): void
    {
        Mail::fake();

        $data = [
            'name' => 'Test User',
            'email' => 'testuser@example.com',
            'subject' => 'tech-support',
            'message' => 'This is a test to verify reply-to address.',
        ];

        $this->postJson('/api/v1/contact', $data);

        Mail::assertQueued(ContactFormNotification::class, function ($mail) {
            return $mail->hasReplyTo('testuser@example.com', 'Test User');
        });
    }
}
