<?php

namespace App\Models\Employees;

use App\Models\Settings\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeStore extends Model
{
    //
    protected $fillable = [
        'status',
        'user_id',
        'store_id',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
