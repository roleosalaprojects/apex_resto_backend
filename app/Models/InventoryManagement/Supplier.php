<?php

namespace App\Models\InventoryManagement;

use App\Models\Products\Item;
use App\Traits\Auditable;
use App\Traits\SerializesDateToAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use Auditable, HasFactory, SerializesDateToAppTimezone;

    //
    protected $fillable = [
        'name',
        'tin',
        'contact',
        'number',
        'email',
        'address',
        'city',
        'zip',
        'province',
        'note',
        'status',
        'user_id',
        'country',
        'payment_terms_days',
    ];

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }
}
