<?php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tax extends Model
{
    use HasFactory;

    //
    protected $fillable = [
        'name', 'rate', 'status', 'user_id',
    ];

    public function items(): HasMany
    {
        return $this->hasMany('items');
    }
}
