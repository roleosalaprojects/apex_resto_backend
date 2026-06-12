<?php

namespace App\Models\CustomerRelations;

use App\Models\Settings\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerCreditTransaction extends Model
{
    protected $fillable = [
        'customer_id',
        'type',
        'amount',
        'balance_after',
        'due_date',
        'payment_method',
        'bank_id',
        'reference_number',
        'reference_type',
        'reference_id',
        'notes',
        'pos_id',
        'store_id',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'due_date' => 'date',
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
