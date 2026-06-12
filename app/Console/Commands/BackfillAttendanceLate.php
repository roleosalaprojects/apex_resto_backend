<?php

namespace App\Console\Commands;

use App\Models\Employees\AttendanceRecord;
use Illuminate\Console\Command;

class BackfillAttendanceLate extends Command
{
    protected $signature = 'attendance:backfill-late';

    protected $description = 'Recalculate is_late and late_minutes for all attendance records';

    public function handle(): int
    {
        $records = AttendanceRecord::with('user')->whereNotNull('time_in')->get();
        $updated = 0;

        foreach ($records as $record) {
            $lateData = $record->calculateLate();

            if ($lateData['is_late'] !== (bool) $record->is_late || $lateData['late_minutes'] !== (int) $record->late_minutes) {
                $record->update([
                    'is_late' => $lateData['is_late'],
                    'late_minutes' => $lateData['late_minutes'],
                ]);
                $updated++;
                $this->info("Updated #{$record->id}: {$record->user->name} time_in={$record->time_in->format('h:i A')} → late=".($lateData['is_late'] ? "YES ({$lateData['late_minutes']} min)" : 'NO'));
            }
        }

        $this->info("Done. Updated {$updated}/{$records->count()} records.");

        return self::SUCCESS;
    }
}
