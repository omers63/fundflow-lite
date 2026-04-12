<?php

namespace Tests\Feature;

use App\Models\Loan;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanComputationTest extends TestCase
{
    use RefreshDatabase;

    public function test_installment_count_uses_member_fund_covering_master_and_settlement(): void
    {
        // 12K loan, 10K in fund → 2K master + 16% × 12K settlement = 3,920 → ceil / 1K = 4
        $count = Loan::computeInstallmentsCount(12000.0, 10000.0, 1000.0, 0.16);
        $this->assertSame(4, $count);
    }

    public function test_installment_count_clamps_negative_fund_balance(): void
    {
        $count = Loan::computeInstallmentsCount(12000.0, -2000.0, 1000.0, 0.16);
        $this->assertSame(4, $count);
    }

    public function test_exemption_defers_first_repayment_by_one_month_after_anchor(): void
    {
        // Default cycle starts day 6 → cutoff day 5. Disburse Feb 4: anchor first = Feb, then +1 → Mar.
        $result = Loan::computeExemptionAndFirstRepayment(Carbon::parse('2026-02-04 10:00:00'));
        $this->assertSame(1, $result['exempted_month']);
        $this->assertSame(2026, $result['exempted_year']);
        $this->assertSame(3, $result['first_repayment_month']);
        $this->assertSame(2026, $result['first_repayment_year']);
    }

    public function test_exemption_late_in_month_still_advances_first_repayment(): void
    {
        // Feb 20: anchor first = Mar, then +1 → Apr.
        $result = Loan::computeExemptionAndFirstRepayment(Carbon::parse('2026-02-20 10:00:00'));
        $this->assertSame(2, $result['exempted_month']);
        $this->assertSame(2026, $result['exempted_year']);
        $this->assertSame(4, $result['first_repayment_month']);
        $this->assertSame(2026, $result['first_repayment_year']);
    }
}
