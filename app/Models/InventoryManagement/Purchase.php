<?php

namespace App\Models\InventoryManagement;

use App\Models\Settings\Store;
use App\Models\User;
use App\Traits\Auditable;
use App\Traits\SerializesDateToAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    use Auditable, HasFactory, SerializesDateToAppTimezone;

    //
    // Approval status constants
    const APPROVAL_DRAFT = 0;

    const APPROVAL_PENDING = 1;

    const APPROVAL_APPROVED = 2;

    const APPROVAL_REJECTED = 3;

    // Payment status constants
    const PAYMENT_UNPAID = 0;

    const PAYMENT_PARTIAL = 1;

    const PAYMENT_PAID = 2;

    protected $fillable = [
        'po',
        'supplier_id',
        'store_id',
        'purchased',
        'expected',
        'note',
        'total',
        'amount_paid',
        'items',
        'received',
        'status',
        'user_id',
        'created_by',
        'received_by',
        'invoice_no',
        'payment_status',
        'payment_type',
        'date_issued',
        'issued_to',
        'issued_by',
        'amount',
        'approval_status',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'double',
            'amount_paid' => 'double',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseLine::class);
    }

    public function adds(): HasMany
    {
        return $this->hasMany(PurchaseAdd::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by', 'id');
    }

    /**
     * Get all approval records for this purchase
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(PurchaseApproval::class)->orderBy('created_at', 'desc');
    }

    /**
     * Get the latest approval record
     */
    public function latestApproval()
    {
        return $this->hasOne(PurchaseApproval::class)->latestOfMany();
    }

    /**
     * Check if purchase is pending approval
     */
    public function isPendingApproval(): bool
    {
        return $this->approval_status === self::APPROVAL_PENDING;
    }

    /**
     * Check if purchase is approved
     */
    public function isApproved(): bool
    {
        return $this->approval_status === self::APPROVAL_APPROVED;
    }

    /**
     * Check if purchase is rejected
     */
    public function isRejected(): bool
    {
        return $this->approval_status === self::APPROVAL_REJECTED;
    }

    /**
     * Check if items can be received (only approved POs)
     */
    public function canReceiveItems(): bool
    {
        return $this->isApproved();
    }

    /**
     * Get all payments for this purchase
     */
    public function payments(): HasMany
    {
        return $this->hasMany(PurchasePayment::class)->orderBy('payment_date', 'desc');
    }

    /**
     * Get the remaining balance to be paid
     */
    public function getRemainingBalanceAttribute(): float
    {
        return max(0, ($this->total ?? 0) - ($this->amount_paid ?? 0));
    }

    /**
     * Check if this PO can accept payments
     * Only approved POs that are not fully paid can accept payments
     */
    public function canAcceptPayment(): bool
    {
        return $this->isApproved() && $this->payment_status !== self::PAYMENT_PAID;
    }

    /**
     * Check if the PO is fully paid
     */
    public function isFullyPaid(): bool
    {
        return $this->payment_status === self::PAYMENT_PAID;
    }

    /**
     * Update payment status based on amount_paid vs total
     */
    public function updatePaymentStatus(): void
    {
        $amountPaid = $this->amount_paid ?? 0;
        $total = $this->total ?? 0;

        if ($amountPaid <= 0) {
            $this->payment_status = self::PAYMENT_UNPAID;
        } elseif ($amountPaid >= $total) {
            $this->payment_status = self::PAYMENT_PAID;
        } else {
            $this->payment_status = self::PAYMENT_PARTIAL;
        }

        $this->save();
    }

    /**
     * Recalculate amount_paid from payments and update status
     */
    public function recalculatePayments(): void
    {
        $this->amount_paid = $this->payments()->sum('amount');
        $this->save();
        $this->updatePaymentStatus();
    }

    /**
     * Get payment status label
     */
    public function getPaymentStatusLabelAttribute(): string
    {
        return match ($this->payment_status) {
            self::PAYMENT_UNPAID => 'Unpaid',
            self::PAYMENT_PARTIAL => 'Partial',
            self::PAYMENT_PAID => 'Paid',
            default => 'Unknown',
        };
    }
}
