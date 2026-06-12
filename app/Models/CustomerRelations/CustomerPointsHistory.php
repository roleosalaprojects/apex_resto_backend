<?php

namespace App\Models\CustomerRelations;

use App\Models\Settings\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerPointsHistory extends Model
{
    use HasFactory;

    protected $table = 'customer_points_history';

    protected $fillable = [
        'customer_id',
        'type',
        'points',
        'balance_after',
        'reference_type',
        'reference_id',
        'reference_number',
        'description',
        'store_id',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'decimal:2',
            'balance_after' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
