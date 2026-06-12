<?php

namespace App\Models\InventoryManagement;

use App\Models\User;
use App\Traits\SerializesDateToAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseApproval extends Model
{
    use HasFactory, SerializesDateToAppTimezone;

    protected $fillable = [
        'purchase_id',
        'status',
        'approved_by',
        'approved_at',
        'rejection_comment',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    /**
     * The purchase this approval belongs to
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /**
     * The user who approved/rejected this purchase
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by', 'id');
    }

    /**
     * Check if approval is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if approved
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if rejected
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
}
