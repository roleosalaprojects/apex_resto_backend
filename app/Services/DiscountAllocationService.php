<?php

namespace App\Services;

/**
 * Senior-Citizen / PWD group discount allocation for dine-in (RMC 38-2012).
 *
 * When a bill is shared by a group, only the beneficiaries' proportional
 * share of the bill is eligible for the 20% discount and VAT exemption.
 * The discountable share is total ÷ pax × beneficiaryCount.
 */
class DiscountAllocationService
{
    private const VAT_RATE = 0.12;

    private const DEFAULT_DISCOUNT_RATE = 0.20;

    /**
     * Allocate the group discount for a VAT-inclusive bill total.
     *
     * @return array{
     *     discountable_share: float,
     *     vat_exempt_sales: float,
     *     vat_amount_removed: float,
     *     discount_amount: float,
     *     net_due: float
     * }
     */
    public function allocateGroupDiscount(
        float $total,
        int $pax,
        int $beneficiaryCount,
        float $rate = self::DEFAULT_DISCOUNT_RATE,
    ): array {
        if ($pax < 1 || $beneficiaryCount < 1 || $total <= 0) {
            return [
                'discountable_share' => 0.0,
                'vat_exempt_sales' => 0.0,
                'vat_amount_removed' => 0.0,
                'discount_amount' => 0.0,
                'net_due' => round($total, 2),
            ];
        }

        $beneficiaryCount = min($beneficiaryCount, $pax);

        // Beneficiaries' proportional share of the VAT-inclusive bill.
        $discountableShare = $total / $pax * $beneficiaryCount;

        // Strip VAT from the beneficiaries' share (they are VAT-exempt).
        $vatExemptSales = $discountableShare / (1 + self::VAT_RATE);
        $vatRemoved = $discountableShare - $vatExemptSales;

        // 20% discount applies to the VAT-exempt (net of VAT) amount.
        $discountAmount = $vatExemptSales * $rate;

        $netDue = $total - $vatRemoved - $discountAmount;

        return [
            'discountable_share' => round($discountableShare, 2),
            'vat_exempt_sales' => round($vatExemptSales, 2),
            'vat_amount_removed' => round($vatRemoved, 2),
            'discount_amount' => round($discountAmount, 2),
            'net_due' => round($netDue, 2),
        ];
    }
}
