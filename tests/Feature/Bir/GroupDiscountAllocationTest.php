<?php

namespace Tests\Feature\Bir;

use App\Services\DiscountAllocationService;
use Tests\TestCase;

class GroupDiscountAllocationTest extends TestCase
{
    private DiscountAllocationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DiscountAllocationService::class);
    }

    public function test_single_diner_full_bill_is_discountable(): void
    {
        // 1 SC dining alone, ₱1,120 VAT-inclusive bill.
        $result = $this->service->allocateGroupDiscount(1120, 1, 1);

        $this->assertEquals(1120.00, $result['discountable_share']);
        $this->assertEquals(1000.00, $result['vat_exempt_sales']);
        $this->assertEquals(120.00, $result['vat_amount_removed']);
        $this->assertEquals(200.00, $result['discount_amount']);
        // 1120 - 120 VAT - 200 discount = 800
        $this->assertEquals(800.00, $result['net_due']);
    }

    public function test_only_beneficiary_share_is_discounted_in_a_group(): void
    {
        // 1 SC among 4 diners, ₱4,480 bill -> ₱1,120 share.
        $result = $this->service->allocateGroupDiscount(4480, 4, 1);

        $this->assertEquals(1120.00, $result['discountable_share']);
        $this->assertEquals(120.00, $result['vat_amount_removed']);
        $this->assertEquals(200.00, $result['discount_amount']);
        // 4480 - 120 - 200 = 4160
        $this->assertEquals(4160.00, $result['net_due']);
    }

    public function test_beneficiary_count_is_capped_at_pax(): void
    {
        $capped = $this->service->allocateGroupDiscount(1120, 2, 5);
        $full = $this->service->allocateGroupDiscount(1120, 2, 2);

        $this->assertEquals($full['discount_amount'], $capped['discount_amount']);
    }

    public function test_zero_beneficiaries_returns_no_discount(): void
    {
        $result = $this->service->allocateGroupDiscount(1120, 4, 0);

        $this->assertEquals(0.0, $result['discount_amount']);
        $this->assertEquals(1120.00, $result['net_due']);
    }
}
