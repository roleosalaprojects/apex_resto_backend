<?php

namespace App\Models\Settings;

use App\Models\Pos\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pos extends Model
{
    use HasFactory;

    //
    protected $fillable = [
        'name',
        'store_id',
        'status',
        'mac',
        'number',
        'user_id',
        'serial',
        'min',
        'ptu',
        'issued',
        'expiry',
        'type',
        'reset_counter',
    ];

    // public function sales(){
    //     return $this->hasMany(Sale::class, 'pos_id', 'id');
    // }
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'pos_id', 'id');
    }
}
