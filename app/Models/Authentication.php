<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Authentication extends Model
{
    use HasFactory, SoftDeletes;

    /*
     * Status
     * denied = Request for Approval is Denied.
     * approved = Request for Approval is Approved.
     * */

    protected $fillable = [
        'pos_id',
        'requested_by', // Requested by User of Cookie POS
        'auth_type', // Authorization types: discount = Request for Apply Discount, allow_remote_cart = Allow to remove items from cart,
        'status', // Status: denied = Denied, approved = Approved, null = Pending, timed-out = User Time exceeded the allowable time to grant or deny the request.
        'consignee_id', // User requested by User of Cookie POS. This can be an superadmin or a person with rights to apply discounts.
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by', 'id');
    }

    public function consignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consignee_id', 'id');
    }
}
