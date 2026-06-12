<?php

namespace App\Models\Products;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use HasFactory;

    //
    protected $fillable = ['name', 'user_id', 'status'];

    public function itemUnits()
    {
        return $this->hasMany(ItemUnit::class);
    }
}
