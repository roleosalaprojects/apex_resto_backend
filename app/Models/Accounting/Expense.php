<?php

namespace App\Models\Accounting;

use App\Models\InventoryManagement\Supplier;
use App\Models\Settings\Store;
use App\Models\User;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    public const STATUS_ACTIVE = 1;

    public const STATUS_VOIDED = 0;

    protected $fillable = [
        'reference_number',
        'expense_category_id',
        'store_id',
        'bank_id',
        'bank_transaction_id',
        'payee',
        'amount',
        'expense_date',
        'description',
        'receipt_number',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
        'voided_at',
        'voided_by',
        'void_reason',
        'receipt_photo',
        'supplier_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'double',
            'expense_date' => 'date',
            'approved_at' => 'datetime',
            'voided_at' => 'datetime',
            'status' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function bankTransaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public static function generateReferenceNumber(): string
    {
        $prefix = 'EXP';
        $date = now()->format('Ymd');
        $random = strtoupper(substr(uniqid(), -6));

        return "{$prefix}-{$date}-{$random}";
    }

    public function getStatusNameAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_VOIDED => 'Voided',
            default => 'Unknown',
        };
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isVoided(): bool
    {
        return $this->status === self::STATUS_VOIDED;
    }

    /**
     * Public-disk URL for the receipt photo, or null if none attached.
     * Returns absolute URL so API clients can render directly.
     */
    public function getReceiptPhotoUrlAttribute(): ?string
    {
        return $this->receipt_photo ? asset($this->receipt_photo) : null;
    }
}
