<?php

namespace App\Models\Accounting;

use App\Models\User;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankTransaction extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    // Transaction types
    public const TYPE_DEPOSIT = 1;

    public const TYPE_WITHDRAWAL = 2;

    public const TYPE_TRANSFER_OUT = 3;

    public const TYPE_TRANSFER_IN = 4;

    protected $fillable = [
        'reference_number',
        'bank_id',
        'transfer_to_bank_id',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'description',
        'payee',
        'proof_photo',
        'transaction_date',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'amount' => 'double',
            'balance_before' => 'double',
            'balance_after' => 'double',
        ];
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function transferToBank(): BelongsTo
    {
        return $this->belongsTo(Bank::class, 'transfer_to_bank_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function generateReferenceNumber(): string
    {
        $prefix = 'TXN';
        $date = now()->format('Ymd');
        $random = strtoupper(substr(uniqid(), -6));

        return "{$prefix}-{$date}-{$random}";
    }

    public function getTypeNameAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_DEPOSIT => 'Deposit',
            self::TYPE_WITHDRAWAL => 'Withdrawal',
            self::TYPE_TRANSFER_OUT => 'Transfer Out',
            self::TYPE_TRANSFER_IN => 'Transfer In',
            default => 'Unknown',
        };
    }

    public function isDebit(): bool
    {
        return in_array($this->type, [self::TYPE_WITHDRAWAL, self::TYPE_TRANSFER_OUT]);
    }

    public function isCredit(): bool
    {
        return in_array($this->type, [self::TYPE_DEPOSIT, self::TYPE_TRANSFER_IN]);
    }

    /**
     * Public-disk URL for the deposit slip / transfer proof, or null if none.
     */
    public function getProofPhotoUrlAttribute(): ?string
    {
        return $this->proof_photo ? asset($this->proof_photo) : null;
    }
}
