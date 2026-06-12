<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;

class OrderShipped extends Mailable
{
    use Queueable, SerializesModels;

    private String $message;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(public string $payload)
    {
        $message = $this->payload;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('test.mails.sample_mail')->with([
            'orderName' => 'Sample Order',
            'orderPrice' => '$10,000',
        ]);
    }

    /**
     * Get the message envelope.
     */
//    public function envelope(): Envelope
//    {
//        return new Envelope(
//            from: new Address('jeffrey@example.com', 'Jeffrey Way'),
//            replyTo: [
//                new Address('roleosala@gmail.com', 'Richard Leosala'),
//            ],
//            subject: 'Order Shipped',
//        );
//    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'test.mails.sample_mail',
            with: [
                'orderName' => 'Sample Order',
                'orderPrice' => '$10,000',
                'url' => route('purchases.show', 1)
            ],
        );
    }
}
