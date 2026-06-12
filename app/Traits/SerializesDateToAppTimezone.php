<?php

namespace App\Traits;

use DateTimeInterface;

trait SerializesDateToAppTimezone
{
    /**
     * Prepare a date for array / JSON serialization.
     * Uses the application timezone instead of UTC.
     */
    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }
}
