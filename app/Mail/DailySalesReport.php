<?php

namespace App\Mail;

use App\Services\ReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DailySalesReport extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array{summary: array, peak_hours_summary: array, margin_alerts: array}  $reportData
     */
    public function __construct(public array $reportData, public string $date) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new \Illuminate\Mail\Mailables\Address(config('mail.from.address'), 'Apex Backend'),
            subject: 'Apex Report - Daily Sales - '.$this->date,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.daily-sales-report',
            with: [
                'data' => $this->reportData,
                'date' => $this->date,
            ]
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $soldItems = $this->reportData['sold_items'] ?? [];

        if (empty($soldItems)) {
            return [];
        }

        $csv = app(ReportService::class)->generateSoldItemsCsv($soldItems);

        return [
            Attachment::fromData(fn () => $csv, "sales-by-item-{$this->date}.csv")
                ->withMime('text/csv'),
        ];
    }
}
