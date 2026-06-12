<?php

namespace App\Models\CustomerRelations;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SpecialCustomer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'identifier',
        'tin',
        'type',
        'child_name',
        'child_age',
    ];
}
