<?php

namespace App\Models\Employees;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftBreak extends Model
{
    use HasFactory;

    protected $fillable = [
        'shift_id',
        'break_start',
        'break_end',
        'type',
        'reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'break_start' => 'datetime',
            'break_end' => 'datetime',
        ];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function isActive(): bool
    {
        return $this->break_end === null;
    }

    public function getDurationMinutesAttribute(): ?int
    {
        if (! $this->break_end) {
            return null;
        }

        return $this->break_start->diffInMinutes($this->break_end);
    }
}
