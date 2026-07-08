<?php

namespace App\Models\Pos;

use App\Models\Accounting\Bank;
use App\Models\CustomerRelations\Customer;
use App\Models\Ecommerce\EcommerceOrder;
use App\Models\Settings\Pos as PosTerminal;
use App\Models\Settings\Store;
use App\Models\User;
use App\Traits\Auditable;
use App\Traits\SerializesDateToAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Sale extends Model
{
    use Auditable, HasFactory, SerializesDateToAppTimezone;

    /**
     * High-churn / redundant fields excluded from audit diffs. The BIR
     * money/breakdown columns stay audited; only framework + denormalised
     * receipt copy fields are dropped.
     *
     * @var array<int, string>
     */
    protected array $excludedAuditFields = [
        'header',
        'footer',
    ];

    /** Cash tendered at point of sale. */
    public const PAYMENT_CASH = 1;

    /** E-wallet (GCash and similar). */
    public const PAYMENT_EWALLET = 2;

    /** Credit sale — customer pays later, charged against credit_balance. */
    public const PAYMENT_CREDIT = 3;

    /** Direct bank transfer (admin-recorded cashless). */
    public const PAYMENT_BANK_TRANSFER = 4;

    /** Cheque (admin-recorded cashless) — see cheque_status for clearing state. */
    public const PAYMENT_CHEQUE = 5;

    /** Card (credit/debit terminal). */
    public const PAYMENT_CARD = 6;

    /** Gift certificate / gift card redemption. */
    public const PAYMENT_GIFT_CERT = 7;

    /** Multi-tender sale — the per-tender split lives in sale_payments. */
    public const PAYMENT_MULTI = 8;

    /**
     * Tender types accepted inside a multi-tender payment. Credit and
     * cheque are excluded: both drive single-tender ledger workflows
     * (credit_balance charge, cheque clearing FSM) that don't split
     * across one receipt.
     *
     * @var array<int, int>
     */
    public const MULTI_TENDER_TYPES = [
        self::PAYMENT_CASH,
        self::PAYMENT_EWALLET,
        self::PAYMENT_BANK_TRANSFER,
        self::PAYMENT_CARD,
        self::PAYMENT_GIFT_CERT,
    ];

    /** Cheque written, not yet cleared. No bank impact yet. */
    public const CHEQUE_PENDING = 'pending';

    /** Drawee bank paid — BankTransaction now exists, balance updated. */
    public const CHEQUE_CLEARED = 'cleared';

    /** Drawee bank refused — customer charged back via credit ledger. */
    public const CHEQUE_BOUNCED = 'bounced';

    protected $fillable = [
        'counter',
        'txn_no',
        'void_no',
        'return_no',
        'is_training',
        'reprint_count',
        'last_reprinted_at',
        'last_reprinted_by',
        'son',
        'total',
        'cash',
        'change',
        'vatable',
        'vat',
        'non_vat',
        'zero_rated',
        'header',
        'footer',
        'type',
        'order_type',
        'table_id',
        'pax',
        'sc_count',
        'pwd_count',
        'sales_by',
        'pos_id',
        'store_id',
        'user_id',
        'created_at',
        'updated_at',
        'sale_id',
        'customer_id',
        'cancelled',
        'approved_by',
        'profit',
        'vat_exempt',
        'zreading_id',
        'discount',
        'sc_discount',
        'pwd_discount',
        'sp_discount',
        'naac_discount',
        'vat_special_discounts',
        'refunds',
        'sale_type',
        'vat_exempt',
        'excess_non_vat',
        'excess_vat',
        'excess_vatable',
        'acquired_points',
        'points_used',
        'special_discount_type',
        'special_discount_name',
        'special_discount_id',
        'special_discount_tin',
        'special_discount_child_name',
        'special_discount_child_birthdate',
        'special_discount_child_age',
        // Payment Reference Type
        'payment_type',
        'cheque_status',
        'reference_number',
        'bank_amount',
        'bank_id',
        'ecommerce_order_id',
        // Voucher
        'voucher_id',
        'voucher_code',
        'voucher_discount',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_training' => 'boolean',
            'last_reprinted_at' => 'datetime',
        ];
    }

    public function sold_by(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'sales_by');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'id');
    }

    public function pos(): BelongsTo
    {
        return $this->belongsTo(PosTerminal::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SaleLine::class, 'sales_id', 'id');
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function refund(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id', 'id');
    }

    /**
     * Sales that refund THIS one — the inverse of refund(). One sale
     * can carry many partial refunds (or one full one), each linked
     * via sale_id. Used by the ecommerce admin order page to render
     * a "Refunded — Sale #N" badge alongside the original.
     */
    public function refundSales(): HasMany
    {
        return $this->hasMany(Sale::class, 'sale_id', 'id')
            ->where('type', true);
    }

    public function ecommerceOrder(): BelongsTo
    {
        return $this->belongsTo(EcommerceOrder::class);
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function paymentProofs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SalePaymentProof::class);
    }

    /**
     * Per-tender breakdown of a multi-tender sale (payment_type =
     * PAYMENT_MULTI). Empty for single-tender sales.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class, 'sales_id', 'id');
    }
}
