<?php

namespace App\Models\InventoryManagement;

use App\Models\Accounting\Bank;
use App\Models\Accounting\BankTransaction;
use App\Models\User;
use App\Traits\Auditable;
use App\Traits\SerializesDateToAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchasePayment extends Model
{
    use Auditable, HasFactory, SerializesDateToAppTimezone, SoftDeletes;

    // Payment method constants
    public const METHOD_CASH = 1;

    public const METHOD_CHECK = 2;

    public const METHOD_BANK_TRANSFER = 3;

    public const METHOD_EWALLET = 4;

    protected $fillable = [
        'reference_number',
        'purchase_id',
        'bank_id',
        'bank_transaction_id',
        'amount',
        'payment_date',
        'payment_method',
        'check_number',
        'notes',
        'created_by',
    ];

    protected $appends = [
        'payment_method_name',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'double',
        ];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function bankTransaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function generateReferenceNumber(): string
    {
        $prefix = 'PAY';
        $date = now()->format('Ymd');
        $random = strtoupper(substr(uniqid(), -6));

        return "{$prefix}-{$date}-{$random}";
    }

    public function getPaymentMethodNameAttribute(): string
    {
        return match ($this->payment_method) {
            self::METHOD_CASH => 'Cash',
            self::METHOD_CHECK => 'Check',
            self::METHOD_BANK_TRANSFER => 'Bank Transfer',
            self::METHOD_EWALLET => 'E-Wallet',
            default => 'Unknown',
        };
    }

    public static function paymentMethods(): array
    {
        return [
            ['value' => self::METHOD_CASH, 'label' => 'Cash'],
            ['value' => self::METHOD_CHECK, 'label' => 'Check'],
            ['value' => self::METHOD_BANK_TRANSFER, 'label' => 'Bank Transfer'],
            ['value' => self::METHOD_EWALLET, 'label' => 'E-Wallet'],
        ];
    }
}
