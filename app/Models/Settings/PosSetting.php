<?php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\Model;

class PosSetting extends Model
{
    //
    protected $fillable = [
        'notif',
        'allow',
        'user_id',
    ];
}
