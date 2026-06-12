<?php

namespace App\Models\Ecommerce;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EcommerceOrderStatusChange extends Model
{
    use HasFactory;

    /** Only created_at is meaningful — these rows are immutable history. */
    public $timestamps = false;

    protected $fillable = [
        'ecommerce_order_id',
        'from_status',
        'to_status',
        'changed_by',
        'note',
        'sms_notified_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => 'integer',
            'to_status' => 'integer',
            'sms_notified_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(EcommerceOrder::class, 'ecommerce_order_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Human label for the from_status — re-uses EcommerceOrder's
     * shared statusLabel mapping by tiny temp instance.
     */
    public function fromLabel(): ?string
    {
        if ($this->from_status === null) {
            return null;
        }

        return (new EcommerceOrder(['status' => $this->from_status]))->statusLabel();
    }

    public function toLabel(): string
    {
        return (new EcommerceOrder(['status' => $this->to_status]))->statusLabel();
    }

    public function toBadgeVariant(): string
    {
        return (new EcommerceOrder(['status' => $this->to_status]))->statusBadgeVariant();
    }
}
