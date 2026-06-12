<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Single-row settings table holding tenant-wide configuration that's too
 * sparse / too wide to model as columns. No user_id column — this code
 * base operates as a single-tenant deployment, matching the precedent
 * set by banks/expenses/vouchers/etc.
 *
 * @property array<string, mixed>|null $thresholds
 * @property array<string, mixed>|null $expense_rules
 * @property array<string, mixed>|null $supplier_rules
 */
class BusinessSettings extends Model
{
    use Auditable, HasFactory;

    protected $table = 'business_settings';

    protected $fillable = [
        'thresholds',
        'expense_rules',
        'supplier_rules',
    ];

    protected function casts(): array
    {
        return [
            'thresholds' => 'array',
            'expense_rules' => 'array',
            'supplier_rules' => 'array',
        ];
    }

    /**
     * Singleton accessor. Returns the one-and-only settings row, creating
     * an empty one on first access so callers always get a valid model.
     */
    public static function current(): self
    {
        return static::query()->oldest('id')->first()
            ?? static::query()->create([]);
    }

    /**
     * Documented defaults that get merged on top of whatever's stored, so
     * the bot/UI always sees a consistent shape and never has to handle
     * NULL where a number is expected.
     *
     * @return array{thresholds: array<string, mixed>, expense_rules: array<string, mixed>, supplier_rules: array<string, mixed>}
     */
    public static function defaults(): array
    {
        return [
            'thresholds' => [
                // low_stock_qty moved to items.low_stock_threshold (per item).
                // bank_alert_floors moved to banks.low_balance_threshold (per bank).
                // expense_confirmation_above intentionally removed.
                'daily_sales_floor' => null,
                'daily_sales_check_after' => '18:00',
            ],
            'expense_rules' => [
                'default_expense_bank_id' => null,
                'receipt_required_above' => null,
                'preferred_categories' => [],
            ],
            'supplier_rules' => [
                'default_supplier_id' => null,
                'treat_supplier_payments_as_expenses' => true,
            ],
        ];
    }

    /**
     * Settings with defaults merged in. Stored values override defaults
     * key-by-key so the response is always complete.
     *
     * @return array{thresholds: array<string, mixed>, expense_rules: array<string, mixed>, supplier_rules: array<string, mixed>}
     */
    public function withDefaults(): array
    {
        $defaults = static::defaults();

        return [
            'thresholds' => array_replace($defaults['thresholds'], $this->thresholds ?? []),
            'expense_rules' => array_replace($defaults['expense_rules'], $this->expense_rules ?? []),
            'supplier_rules' => array_replace($defaults['supplier_rules'], $this->supplier_rules ?? []),
        ];
    }
}
