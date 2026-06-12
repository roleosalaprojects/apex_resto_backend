<?php

namespace App\Models\Products;

use Illuminate\Database\Eloquent\Model;

class Discount extends Model
{
    //
    protected $fillable = [
        'name',
        'rate',
        'user_id',
        'status',
    ];
}
