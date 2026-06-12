<?php

namespace App\Models\Employees;

use App\Models\Settings\Pos;
use App\Models\Settings\Store;
use App\Models\User;
use App\Traits\SerializesDateToAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    use HasFactory, SerializesDateToAppTimezone;

    protected $fillable = [
        'user_id',
        'pos_id',
        'store_id',
        'clock_in',
        'clock_out',
        'starting_cash',
        'ending_cash',
        'expected_cash',
        'cash_difference',
        'notes',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'clock_in' => 'datetime',
            'clock_out' => 'datetime',
            'starting_cash' => 'decimal:2',
            'ending_cash' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'cash_difference' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pos(): BelongsTo
    {
        return $this->belongsTo(Pos::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function breaks(): HasMany
    {
        return $this->hasMany(ShiftBreak::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getTotalBreakMinutesAttribute(): int
    {
        return $this->breaks
            ->filter(fn ($break) => $break->break_end !== null)
            ->sum(fn ($break) => $break->break_start->diffInMinutes($break->break_end));
    }

    public function getWorkedMinutesAttribute(): ?int
    {
        if (! $this->clock_in || ! $this->clock_out) {
            return null;
        }

        $totalMinutes = $this->clock_in->diffInMinutes($this->clock_out);

        return $totalMinutes - $this->total_break_minutes;
    }
}
