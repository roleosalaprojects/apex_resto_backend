<?php

namespace App\Mail\Purchases;

use App\Purchase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class PerformAction extends Mailable
{
    use Queueable, SerializesModels;

    private Collection $purchases;

    /**
     * Create a new message instance.
     */
    public function __construct(Collection $purchases)
    {
        $this->purchases = $purchases;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Purchase Orders that Needs Actions.',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'users.mails.purchases.perform_actions',
            with: [
                'purchases' => $this->purchases,
            ]
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
