<?php

namespace App\Mail;

use App\Models\CustomerRelations\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactFormNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public ContactMessage $contactMessage) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subjectMap = [
            'web-development' => 'Web Development',
            'mobile-app' => 'Mobile App',
            'pos-system' => 'POS System',
            'api-development' => 'API Development',
            'database-design' => 'Database Design',
            'tech-support' => 'Tech Support',
            'other' => 'Other',
        ];

        $subjectLabel = $subjectMap[$this->contactMessage->subject] ?? $this->contactMessage->subject;

        return new Envelope(
            replyTo: [
                new Address($this->contactMessage->email, $this->contactMessage->name),
            ],
            subject: "[RLCPS Contact] {$subjectLabel} - {$this->contactMessage->name}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.contact-form-notification',
            with: [
                'contact' => $this->contactMessage,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
