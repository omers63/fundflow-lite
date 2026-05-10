<?php

namespace Tests\Feature;

use App\Models\Loan;
use App\Models\Member;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LoanGraceCycleFinalizeTest extends TestCase
{
    use RefreshDatabase;

    private function seedMember(): Member
    {
        $user = User::factory()->create([
            'status' => 'approved',
        ]);

        return Member::create([
            'user_id' => $user->id,
            'member_number' => 'M-GRACE-1',
            'joined_at' => '2026-01-01',
            'status' => 'active',
            'monthly_contribution_amount' => 500,
            'household_email' => 'grace@example.test',
            'is_separated' => false,
            'direct_login_enabled' => false,
        ]);
    }

    private function seedContributionAsOf(int $memberId, int $month, int $year, Carbon $paidAt): void
    {
        DB::table('contributions')->insert([
            'member_id' => $memberId,
            'amount' => 500,
            'month' => $month,
            'year' => $year,
            'paid_at' => $paidAt,
            'payment_method' => 'admin',
            'reference_number' => null,
            'notes' => null,
            'is_late' => false,
            'late_fee_amount' => null,
            'created_at' => $paidAt,
            'updated_at' => $paidAt,
        ]);
    }

    /** Default cycle start day 6 → cutoff 5; disburse after cutoff in Sept. */
    public function test_disburse_september_21_without_september_contribution_keeps_september_grace_first_due_november(): void
    {
        $member = $this->seedMember();

        $disbursedAt = Carbon::parse('2026-09-21 12:00:00');
        $base = Loan::computeExemptionAndFirstRepayment($disbursedAt, true);
        $final = Loan::finalizeExemptionForDisbursement($member, $base, $disbursedAt);

        $this->assertSame(9, $final['exempted_month']);
        $this->assertSame(2026, $final['exempted_year']);
        $this->assertSame(11, $final['first_repayment_month']);
        $this->assertSame(2026, $final['first_repayment_year']);
    }

    public function test_disburse_september_21_with_september_contribution_shifts_grace_to_october_first_due_december(): void
    {
        $member = $this->seedMember();
        $disbursedAt = Carbon::parse('2026-09-21 12:00:00');
        $this->seedContributionAsOf($member->id, 9, 2026, Carbon::parse('2026-09-10 10:00:00'));

        $base = Loan::computeExemptionAndFirstRepayment($disbursedAt, true);
        $final = Loan::finalizeExemptionForDisbursement($member, $base, $disbursedAt);

        $this->assertSame(10, $final['exempted_month']);
        $this->assertSame(2026, $final['exempted_year']);
        $this->assertSame(12, $final['first_repayment_month']);
        $this->assertSame(2026, $final['first_repayment_year']);
    }

    public function test_september_contribution_recorded_after_disbursement_does_not_shift_grace(): void
    {
        $member = $this->seedMember();
        $disbursedAt = Carbon::parse('2026-09-21 12:00:00');
        $this->seedContributionAsOf($member->id, 9, 2026, Carbon::parse('2026-09-22 10:00:00'));

        $base = Loan::computeExemptionAndFirstRepayment($disbursedAt, true);
        $final = Loan::finalizeExemptionForDisbursement($member, $base, $disbursedAt);

        $this->assertSame(9, $final['exempted_month']);
        $this->assertSame(11, $final['first_repayment_month']);
    }

    public function test_member_has_contribution_for_cycle_as_of_uses_paid_at(): void
    {
        $member = $this->seedMember();
        $asOf = Carbon::parse('2026-09-21 12:00:00');

        $this->assertFalse(
            Loan::memberHasContributionForCycleAsOf((int) $member->id, 9, 2026, $asOf)
        );

        $this->seedContributionAsOf($member->id, 9, 2026, Carbon::parse('2026-09-15 08:00:00'));

        $this->assertTrue(
            Loan::memberHasContributionForCycleAsOf((int) $member->id, 9, 2026, $asOf)
        );
    }
}
