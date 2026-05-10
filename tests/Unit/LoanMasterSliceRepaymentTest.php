<?php

namespace Tests\Unit;

use App\Services\AccountingService;
use PHPUnit\Framework\TestCase;

class LoanMasterSliceRepaymentTest extends TestCase
{
    public function test_waterfall_caps_repaid_to_master_at_master_portion(): void
    {
        $this->assertSame(
            5000.0,
            AccountingService::principalAmountCreditingMasterRepaidSlice(10000.0, 0.0, 5000.0)
        );
        $this->assertSame(
            5000.0,
            AccountingService::principalAmountCreditingMasterRepaidSlice(10000.0, 5000.0, 5000.0)
        );
        $this->assertSame(
            0.0,
            AccountingService::principalAmountCreditingMasterRepaidSlice(10000.0, 10000.0, 5000.0)
        );
    }

    public function test_partial_remaining_master_slice(): void
    {
        // Remaining master slice = 7500 − 5000 = 2500; payment 3000 → only 2500 credits repaid_to_master.
        $this->assertSame(
            2500.0,
            AccountingService::principalAmountCreditingMasterRepaidSlice(7500.0, 5000.0, 3000.0)
        );
    }
}
