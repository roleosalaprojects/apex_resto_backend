<?php

namespace App\Models;

use App\Traits\SerializesDateToAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BulkOperationLog extends Model
{
    /** @use HasFactory<\Database\Factories\BulkOperationLogFactory> */
    use HasFactory, SerializesDateToAppTimezone;

    protected $fillable = [
        'type',
        'user_id',
        'total_records',
        'processed_records',
        'success_records',
        'failed_records',
        'status',
        'errors',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'errors' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markAsProcessing(): void
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed(): void
    {
        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
        ]);
    }

    public function incrementProcessed(): void
    {
        $this->increment('processed_records');
    }

    public function incrementSuccess(): void
    {
        $this->increment('success_records');
    }

    public function incrementFailed(): void
    {
        $this->increment('failed_records');
    }

    /**
     * @param  array<string, mixed>  $error
     */
    public function addError(array $error): void
    {
        $errors = $this->errors ?? [];
        $errors[] = $error;
        $this->update(['errors' => $errors]);
    }

    public function getProgressPercentAttribute(): int
    {
        if ($this->total_records === 0) {
            return 0;
        }

        return (int) round(($this->processed_records / $this->total_records) * 100);
    }
}
