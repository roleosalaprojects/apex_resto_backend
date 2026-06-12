<?php

namespace App\Mail;

use App\Purchase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PurchaseOrderNotification extends Mailable
{
    use Queueable, SerializesModels;

    private int $purchase;

    /**
     * Create a new message instance.
     */
    public function __construct($purchase)
    {

        $this->purchase = $purchase;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Purchase Order Due Notification',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $purchase = Purchase::where('id', $this->purchase)
            ->with([
                'supplier',
                'store',
            ])
            ->first();
        return new Content(
            markdown: 'users.mails.purchase_notification',
            with: [
                'purchase' => $purchase,
                'url' => route('purchases.show', $purchase),
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
