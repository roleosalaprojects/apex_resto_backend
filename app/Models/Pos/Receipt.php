<?php

namespace App\Models\Pos;

use Illuminate\Database\Eloquent\Model;

class Receipt extends Model
{
    //
    protected $fillable = [
        'header',
        'footer',
        'vat_reg',
        'tin',
        'user_id',
        'points',
        'name',
        'email',
        'phone',
        'ptu',
        'accredition',
        'display',
        'hocus_pocus',
        'apply',
        'rate',
        'exempt',
    ];
}
