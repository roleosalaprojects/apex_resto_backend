<?php

namespace App\Models\Employees;

use App\Models\Settings\Store;
use App\Models\User;
use App\Services\FcmService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AttendanceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_id',
        'store_id',
        'date',
        'time_in',
        'time_out',
        'hours_rendered',
        'status',
        'is_late',
        'late_minutes',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'time_in' => 'datetime',
            'time_out' => 'datetime',
            'hours_rendered' => 'decimal:2',
            'is_late' => 'boolean',
            'late_minutes' => 'integer',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    // Scopes
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForStore($query, int $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeForDate($query, $date)
    {
        return $query->where('date', $date);
    }

    public function scopePresent($query)
    {
        return $query->where('status', 'present');
    }

    public function scopeAbsent($query)
    {
        return $query->where('status', 'absent');
    }

    public function scopeLate($query)
    {
        return $query->where('is_late', true);
    }

    // Helpers
    public function calculateHours(): float
    {
        if (! $this->time_in || ! $this->time_out) {
            return 0;
        }

        return round($this->time_in->diffInMinutes($this->time_out) / 60, 2);
    }

    public function isPresent(): bool
    {
        return $this->status === 'present';
    }

    public function hasTimedIn(): bool
    {
        return $this->time_in !== null;
    }

    public function hasTimedOut(): bool
    {
        return $this->time_out !== null;
    }

    public function isComplete(): bool
    {
        return $this->hasTimedIn() && $this->hasTimedOut();
    }

    /**
     * Calculate late status based on employee schedule.
     *
     * @return array{is_late: bool, late_minutes: int}
     */
    public function calculateLate(): array
    {
        // Default: not late
        $result = ['is_late' => false, 'late_minutes' => 0];

        // If no time_in, can't be late
        if (! $this->time_in) {
            return $result;
        }

        // Get the employee's schedule for this day
        $user = $this->user;
        if (! $user) {
            return $result;
        }

        $dayOfWeek = $this->date->dayOfWeek; // 0=Sunday, 1=Monday...6=Saturday
        $schedule = $user->getScheduleForDay($dayOfWeek);

        // No schedule for this day = not late (rest day or unscheduled)
        if (! $schedule || ! $schedule->start_time) {
            return $result;
        }

        // Get grace period from config
        $gracePeriod = config('attendance.grace_period', 5);

        // Build the scheduled start datetime for comparison
        $scheduledStart = $this->date->copy()->setTimeFromTimeString($schedule->start_time->format('H:i:s'));
        $cutoffTime = $scheduledStart->copy()->addMinutes($gracePeriod);

        // Compare time_in with cutoff
        if ($this->time_in->gt($cutoffTime)) {
            $result['is_late'] = true;
            $result['late_minutes'] = (int) $scheduledStart->diffInMinutes($this->time_in);
        }

        return $result;
    }

    /**
     * Check if employee is late.
     */
    public function isLate(): bool
    {
        return $this->is_late ?? false;
    }

    /**
     * Send a push notification when an employee clocks in late.
     */
    public function notifyLateClockIn(): void
    {
        if (! $this->is_late || $this->late_minutes <= 0) {
            return;
        }

        $user = $this->user;
        if (! $user) {
            return;
        }

        $storeName = $this->store?->name ?? 'Unknown store';
        $businessUserId = $user->user_id;

        if (! $businessUserId) {
            return;
        }

        try {
            app(FcmService::class)->sendToUsersWithPermission(
                $businessUserId,
                'attndnc',
                'Late Clock-in',
                "{$user->name} clocked in {$this->late_minutes} minutes late at {$storeName}",
                ['type' => 'late_clockin', 'id' => (string) $this->id]
            );
        } catch (\Exception $e) {
            Log::warning('FCM notification failed for late clock-in: '.$e->getMessage());
        }
    }
}
