<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Grace Period
    |--------------------------------------------------------------------------
    |
    | The number of minutes after the scheduled start time before an employee
    | is considered late. Default is 5 minutes.
    |
    */
    'grace_period' => env('ATTENDANCE_GRACE_PERIOD', 5),
];
