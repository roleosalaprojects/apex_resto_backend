<?php

namespace App\Models\Employees;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Schedule extends Model
{
    //
    use SoftDeletes;

    protected $fillable = [
        'name',
        'in',
        'out',
        'user_id',
    ];
}
