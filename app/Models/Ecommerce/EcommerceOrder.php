<?php

namespace App\Models\Ecommerce;

use App\Models\CustomerRelations\Customer;
use App\Models\Pos\Sale;
use App\Models\User;
use App\Traits\SerializesDateToAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class EcommerceOrder extends Model
{
    use HasFactory, SerializesDateToAppTimezone, SoftDeletes;

    /** Customer placed the order; awaiting admin verification. */
    public const STATUS_PENDING = 0;

    /** Admin approved the order; awaiting payment or POS pickup. */
    public const STATUS_VERIFIED = 1;

    /** Terminal state — order will not be fulfilled. */
    public const STATUS_CANCELLED = 2;

    /** Payment received; either via POS ring-up or admin cashless recording. */
    public const STATUS_PAID = 3;

    /** Store is packing / preparing the order for pickup. */
    public const STATUS_PREPARING = 4;

    /** Customer has collected the goods from the store (terminal-happy). */
    public const STATUS_PICKED_UP = 5;

    /** Customer signalled they'll pay cash when picking up. */
    public const PAYMENT_INTENT_CASH_ON_PICKUP = 'cash_on_pickup';

    /** Customer signalled they'll pay via GCash / e-wallet. */
    public const PAYMENT_INTENT_GCASH = 'gcash';

    /** Customer signalled they'll pay via direct bank transfer. */
    public const PAYMENT_INTENT_BANK_TRANSFER = 'bank_transfer';

    /** Customer signalled they'll pay via cheque (rare on /shop, mainly for B2B). */
    public const PAYMENT_INTENT_CHEQUE = 'cheque';

    protected $fillable = [
        'reference',
        'customer_id',
        'total',
        'qty',
        'status',
        'note',
        'payment_intent',
        'verified_by',
        'verified_at',
        'cancelled_by',
        'cancelled_at',
        'is_wholesale',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'is_wholesale' => 'boolean',
            'verified_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(EcommerceOrderLine::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function sale(): HasOne
    {
        // Defensive `created_at` constraint: a real sale for this order
        // can only have been rung up AT OR AFTER the order was placed.
        // Without this, an old sale whose `ecommerce_order_id` matches
        // a recycled order id (e.g. after a TRUNCATE that bypassed the
        // ON DELETE SET NULL via FOREIGN_KEY_CHECKS=0) would render as
        // the new order's "Paid" panel. Filter such phantoms out.
        return $this->hasOne(Sale::class, 'ecommerce_order_id')
            ->when($this->created_at, fn ($q, $orderCreatedAt) => $q->where('sales.created_at', '>=', $orderCreatedAt));
    }

    public function isFulfilled(): bool
    {
        return $this->sale()->exists();
    }

    public function isPending(): bool
    {
        return (int) $this->status === self::STATUS_PENDING;
    }

    public function isVerified(): bool
    {
        return (int) $this->status === self::STATUS_VERIFIED;
    }

    public function isCancelled(): bool
    {
        return (int) $this->status === self::STATUS_CANCELLED;
    }

    public function isPaid(): bool
    {
        return (int) $this->status === self::STATUS_PAID;
    }

    public function isPreparing(): bool
    {
        return (int) $this->status === self::STATUS_PREPARING;
    }

    public function isPickedUp(): bool
    {
        return (int) $this->status === self::STATUS_PICKED_UP;
    }

    /**
     * Human label for the current status — used by both admin and
     * customer views so the wording stays consistent.
     */
    public function statusLabel(): string
    {
        return match ((int) $this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_VERIFIED => 'Verified',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_PAID => 'Paid',
            self::STATUS_PREPARING => 'Preparing',
            self::STATUS_PICKED_UP => 'Picked Up',
            default => 'Unknown',
        };
    }

    /**
     * Bootstrap badge variant for the current status. Mirrors the
     * colour intent on both admin and customer surfaces.
     *
     * "preparing" is a custom variant (not a Bootstrap built-in) —
     * styled as light violet to read as "active work happening" and
     * stay distinct from warning (pending), primary (verified blue),
     * info (paid cyan), success (picked up green), and danger
     * (cancelled). Views that use badge-light-{variant} must include
     * the local .badge-light-preparing rule below.
     */
    public function statusBadgeVariant(): string
    {
        return match ((int) $this->status) {
            self::STATUS_PENDING => 'warning',
            self::STATUS_VERIFIED => 'primary',
            self::STATUS_CANCELLED => 'danger',
            self::STATUS_PAID => 'info',
            self::STATUS_PREPARING => 'preparing',
            self::STATUS_PICKED_UP => 'success',
            default => 'secondary',
        };
    }

    /**
     * Human label for the customer's payment intent. NULL when the
     * customer didn't pick one (legacy orders or "decide at pickup").
     */
    public function paymentIntentLabel(): ?string
    {
        return match ($this->payment_intent) {
            self::PAYMENT_INTENT_CASH_ON_PICKUP => 'Cash on Pickup',
            self::PAYMENT_INTENT_GCASH => 'GCash / E-Wallet',
            self::PAYMENT_INTENT_BANK_TRANSFER => 'Bank Transfer',
            self::PAYMENT_INTENT_CHEQUE => 'Cheque',
            default => null,
        };
    }

    /**
     * Map the customer's intent to the Sale::PAYMENT_* integer used by
     * the Record Payment surface. Lets the admin/dashboard pre-select
     * the matching radio without re-implementing the slug mapping.
     */
    public function intendedSalePaymentType(): ?int
    {
        return match ($this->payment_intent) {
            self::PAYMENT_INTENT_CASH_ON_PICKUP => \App\Models\Pos\Sale::PAYMENT_CASH,
            self::PAYMENT_INTENT_GCASH => \App\Models\Pos\Sale::PAYMENT_EWALLET,
            self::PAYMENT_INTENT_BANK_TRANSFER => \App\Models\Pos\Sale::PAYMENT_BANK_TRANSFER,
            self::PAYMENT_INTENT_CHEQUE => \App\Models\Pos\Sale::PAYMENT_CHEQUE,
            default => null,
        };
    }

    /**
     * Generate a guess-resistant reference number.
     *
     * Uses random_bytes (CSPRNG via the OS) over md5(uniqid()) — the
     * legacy generator combined a microsecond timestamp with mt_rand,
     * yielding 32 bits at best and a guessable timeline. With 48 bits
     * (6 random bytes → 12 hex chars) an attacker walking ECO-* URLs
     * to enumerate orders faces ~280 trillion candidates.
     *
     * Old 8-char references already in the DB stay valid — the route
     * regex (`ECO-[A-Z0-9]+`) and the implicit reference binding both
     * accept any length.
     */
    public static function generateReference(): string
    {
        do {
            $reference = 'ECO-'.strtoupper(bin2hex(random_bytes(6)));
        } while (static::where('reference', $reference)->exists());

        return $reference;
    }

    /**
     * Audit trail of every status transition this order has been
     * through, oldest first so the admin timeline can render it in
     * chronological order.
     */
    public function statusChanges(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        // Tiebreaker by id matters when two transitions are logged
        // within the same Carbon instant (e.g. POS sale advancing
        // PAID + PICKED_UP back-to-back) — without it the timeline
        // ordering becomes undefined.
        return $this->hasMany(EcommerceOrderStatusChange::class)
            ->orderBy('created_at')
            ->orderBy('id');
    }

    /**
     * Photos captured at the moment of pickup — receipt signing,
     * customer holding the goods, handover confirmation.
     */
    public function pickupProofs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EcommerceOrderPickupProof::class)
            ->orderBy('created_at');
    }

    /**
     * Record a status transition. Caller passes the explicit from-status
     * because $order->update() refreshes the model's original attributes,
     * so getOriginal('status') would lie if we read it after the update.
     *
     * Pass from = null for the initial "order created" event.
     */
    public function logStatusChange(?int $fromStatus, int $toStatus, ?int $changedBy = null, ?string $note = null): EcommerceOrderStatusChange
    {
        return EcommerceOrderStatusChange::create([
            'ecommerce_order_id' => $this->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'changed_by' => $changedBy,
            'note' => $note,
            'created_at' => now(),
        ]);
    }
}
