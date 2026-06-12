<?php

namespace App\Models\InventoryManagement;

use App\Models\Settings\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Count extends Model
{
    use HasFactory;

    protected $fillable = [
        'created_by',
        'ic',
        'status',
        'user_id',
        'total',
        'store_id',
    ];

    public function creator(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CountLine::class, 'count_id', 'id');
    }

    public function store(): HasOne
    {
        return $this->hasOne(Store::class, 'id', 'store_id');
    }
}
