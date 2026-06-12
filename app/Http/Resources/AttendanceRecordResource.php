<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceRecordResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'user_id' => $this->user_id,
            'store_id' => $this->store_id,
            'date' => $this->date->toDateString(),
            'formatted_date' => $this->date->format('M d, Y'),
            'day_name' => $this->date->format('l'),
            'time_in' => $this->time_in?->toDateTimeString(),
            'formatted_time_in' => $this->time_in?->format('h:i A'),
            'time_out' => $this->time_out?->toDateTimeString(),
            'formatted_time_out' => $this->time_out?->format('h:i A'),
            'hours_rendered' => (float) $this->hours_rendered,
            'formatted_hours' => number_format($this->hours_rendered, 2).' hrs',
            'status' => $this->status,
            'is_late' => (bool) $this->is_late,
            'late_minutes' => (int) $this->late_minutes,
            'formatted_late' => $this->is_late ? $this->late_minutes.' mins late' : null,
            'remarks' => $this->remarks,
            'has_timed_in' => $this->hasTimedIn(),
            'has_timed_out' => $this->hasTimedOut(),
            'is_complete' => $this->isComplete(),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]),
            'store' => $this->whenLoaded('store', fn () => [
                'id' => $this->store->id,
                'name' => $this->store->name,
            ]),
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
        ];
    }
}
