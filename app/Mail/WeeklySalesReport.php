<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WeeklySalesReport extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array{summary: array, peak_hours_summary: array, margin_alerts: array}  $reportData
     */
    public function __construct(public array $reportData, public string $weekRange) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new \Illuminate\Mail\Mailables\Address(config('mail.from.address'), 'Apex Backend'),
            subject: 'Apex Report - Weekly Sales - '.$this->weekRange,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.weekly-sales-report',
            with: [
                'data' => $this->reportData,
                'weekRange' => $this->weekRange,
            ]
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
