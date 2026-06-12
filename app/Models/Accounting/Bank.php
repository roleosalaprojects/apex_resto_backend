<?php

namespace App\Models\Accounting;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bank extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    // Account Types
    public const TYPE_SAVINGS = 0;

    public const TYPE_CHECKING = 1;

    public const TYPE_CREDIT = 2;

    public const TYPE_PASSBOOK = 3;

    public const TYPE_EWALLET = 4;

    protected $fillable = [
        'bank_name',
        'account_number',
        'account_name',
        'account_type',
        'opening_balance',
        'balance',
        'low_balance_threshold',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'double',
            'balance' => 'double',
            'low_balance_threshold' => 'double',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }

    public function getAccountTypeNameAttribute(): string
    {
        return match ($this->account_type) {
            self::TYPE_SAVINGS => 'Savings',
            self::TYPE_CHECKING => 'Checking',
            self::TYPE_CREDIT => 'Credit',
            self::TYPE_PASSBOOK => 'Passbook',
            self::TYPE_EWALLET => 'E-Wallet',
            default => 'Unknown',
        };
    }

    public function getTotalDepositsAttribute(): float
    {
        return $this->transactions()
            ->whereIn('type', [BankTransaction::TYPE_DEPOSIT, BankTransaction::TYPE_TRANSFER_IN])
            ->sum('amount');
    }

    public function getTotalWithdrawalsAttribute(): float
    {
        return $this->transactions()
            ->whereIn('type', [BankTransaction::TYPE_WITHDRAWAL, BankTransaction::TYPE_TRANSFER_OUT])
            ->sum('amount');
    }
}
