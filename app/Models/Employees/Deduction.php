<?php

namespace App\Models\Employees;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Deduction extends Model
{
    use SoftDeletes;

    //
    protected $fillable = [
        'name',
        'amount',
        'user_id',
    ];
}
