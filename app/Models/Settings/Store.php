<?php

namespace App\Models\Settings;

use App\Models\Pos\Sale;
use App\Models\Products\ItemStore;
use App\Traits\SerializesDateToAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Store extends Model
{
    use HasFactory, SerializesDateToAppTimezone;

    //
    protected $fillable = [
        'name',
        'user_id',
        'status',
        'header',
        'footer',
        'tin',
        'vat_reg',
        'phone',
        'email',
        'latitude',
        'longitude',
        'counter',
    ];

    public function item_store(): HasOne
    {
        return $this->hasOne(ItemStore::class);
    }

    public function stocks(): HasOne
    {
        return $this->hasOne(ItemStore::class);
    }

    public function pos(): HasMany
    {
        return $this->hasMany(Pos::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
