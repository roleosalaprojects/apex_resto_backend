<?php

namespace App\Mail;

use App\Models\Ecommerce\EcommerceOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Fan-out alert to sales/fulfillment staff the moment a customer
 * places an order on /shop. Mirrors the FCM push that fires from the
 * same checkout closure — email is the durable fallback for whoever
 * isn't sitting in front of their phone.
 *
 * ShouldQueue: checkout shouldn't wait on SMTP. Goes through the
 * database queue (per project convention) and sends out-of-band.
 */
class NewEcommerceOrder extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public EcommerceOrder $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new \Illuminate\Mail\Mailables\Address(config('mail.from.address'), 'Apex Backend'),
            subject: "New Order: {$this->order->reference} — ₱".number_format($this->order->total, 2),
        );
    }

    public function content(): Content
    {
        // Reload relations the email view needs so the markdown
        // template stays declarative — no N+1 inside the Blade.
        $order = $this->order->loadMissing(['customer', 'lines.item']);

        return new Content(
            markdown: 'emails.new-ecommerce-order',
            with: [
                'order' => $order,
                'adminUrl' => url('/admin/ecommerce-orders/'.$order->id),
            ],
        );
    }
}
