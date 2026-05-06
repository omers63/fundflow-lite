# Loan Lifecycle Workflows and Accounting

This document describes the full loan lifecycle in FundFlow:

- request
- approval
- rejection/cancellation
- disbursement (partial/full)
- repayment
- delinquency handling
- transfer to guarantor liability / default collection

It also includes the complete ledger/fund posting logic used by the system.

---

## Core Models and Accounts

## Primary Models

- `Loan`
- `LoanInstallment`
- `LoanDisbursement`
- `Member`
- `Account` and `AccountTransaction`

## Account Types Used

- `master_cash`
- `master_fund`
- `member_cash`
- `member_fund`
- `loan` (per-loan account)

---

## 1) Loan Request Workflow

## Entry Points

- Member self-request: `MyLoansResource` (`apply_loan` action)
- Admin create request: `LoanResource` + `CreateLoan`

## What happens

1. Eligibility is validated via `LoanEligibilityService`.
2. Max amount checks are applied (based on fund balance rules).
3. A `Loan` record is created with `status = pending`.

## Key fields set on request

- `member_id`
- `amount_requested`
- `purpose`
- `guarantor_member_id`
- witness fields
- `is_emergency`
- `has_grace_cycle`
- `settlement_threshold`
- `applied_at`

## Ledger posting

- None at request stage.

---

## 2) Approval Workflow

## Entry Point

- Admin action: `LoanResource::approveLoanAction()`

## What happens

1. Admin sets approved amount and optional override approval date.
2. Loan tier and fund tier are resolved.
3. Installment count is computed.
4. Loan is updated to approved.
5. Queue is resequenced by fund tier.

## Status transition

- `pending -> approved`

## Key fields updated

- `status = approved`
- `amount_approved`
- `approved_at`
- `approved_by_id`
- `loan_tier_id`
- `fund_tier_id`
- `installments_count`
- `is_emergency`
- `has_grace_cycle`
- `settlement_threshold`

## Ledger posting

- None at approval stage.

---

## 3) Rejection and Cancellation Workflow

## Rejection

- Admin action: `LoanResource::rejectLoanAction()`
- Updates:
  - `status = rejected`
  - `rejection_reason`

## Cancellation

- Member can cancel pending own request.
- Admin can cancel pending/approved loans.
- Updates:
  - `status = cancelled`
  - `cancellation_reason`

## Status transitions

- `pending -> rejected`
- `pending -> cancelled`
- `approved -> cancelled` (admin)

## Ledger posting

- None for reject/cancel action itself.
- If loan is deleted, `AccountingService::safeDeleteLoan()` reverses related ledger lines.

---

## 4) Disbursement Workflow (Partial and Full)

## Entry Points

- Admin disburse action in `LoanResource::disburseLoanAction()`
- Bank posting path (loan-linked debit transaction)

## Partial disbursement

1. Create `LoanDisbursement`.
2. Post accounting via `AccountingService::postPartialLoanDisbursement(...)`.
3. Increment `loans.amount_disbursed`.
4. Keep loan status as `approved` until fully disbursed.

## Full/final disbursement

When cumulative disbursement reaches approved amount:

1. Loan transitions to `active`.
2. Schedule metadata is computed from disbursement date:
   - grace-cycle behavior (`has_grace_cycle`)
   - first repayment month/year
3. Installment rows are generated.
4. Loan semantic portions (`member_portion`, `master_portion`) are stored.

## Status transition

- `approved -> approved` (partial)
- `approved -> active` (full)

---

## 5) Repayment Workflow

## Scheduled/Open-period repayment path

Handled by `LoanRepaymentService`:

1. Identify installment due in current period.
2. Compute late fee (if overdue).
3. Check member cash sufficiency.
4. Debit member cash (`debitCashForRepayment`).
5. Mark installment paid.
6. Observer posts repayment fund/loan legs.
7. Update late counters if applicable.

## Observer posting

- `LoanInstallmentObserver` listens for first `status -> paid`.
- Calls `AccountingService::postLoanRepayment($installment)`.

## Manual Mark Paid behavior

- Admin installment table `Mark Paid` updates status/time.
- The observer still triggers repayment posting on first transition to paid.

## Early settlement

- `LoanEarlySettlementService` loops all remaining installments:
  - debits required cash
  - marks installments paid
  - observer posts repayment legs per installment
- Loan ends as `early_settled`.

## Settlement completion

- `LoanDefaultService::checkSettlements()` marks loan `completed` when settlement conditions are satisfied.

## Status transitions

- installment `pending|overdue -> paid`
- loan `active -> completed` or `active -> early_settled`

---

## 6) Delinquency Workflow

Handled primarily by:

- `DelinquencyService`
- `MemberDelinquencyEvaluator`

## What happens

1. Overdue installments are marked (`pending -> overdue`).
2. Member delinquency thresholds are evaluated.
3. If breached:
   - member may be suspended
   - guarantor liability transfer timestamp may be set on active loans
4. If recovered:
   - member can return to active
   - liability transfer markers can be cleared

---

## 7) Transfer to Guarantor Liability and Default Collection

Handled by `LoanDefaultService::processDefaults()`.

## What happens

1. Finds overdue unpaid installments.
2. If guarantor liability already transferred, guarantor debit is prioritized.
3. If not transferred, warning/grace cycles are processed first.
4. After grace expires (or transfer applies), guarantor fund is debited.
5. Installment is marked paid by guarantor path.

## Related flags/fields

- `loan.guarantor_liability_transferred_at`
- `loan.guarantor_released_at`
- `installment.paid_by_guarantor`
- late metadata (`is_late`, `late_fee_amount`)

---

## Full Ledger/Fund Posting Matrix

## A) Loan Disbursement (standard partial/full tranche)

Method: `AccountingService::postPartialLoanDisbursement(...)`  
Ledger source: `Loan`

- **Debit** `master_fund` by disbursement amount
- **Debit** `member_fund` by disbursement amount (mirror leg)
- **Debit** `loan` account by disbursement amount
- **Credit** `member_cash` by disbursement amount (cash payout)

Also updates:

- `loan_disbursements.member_portion/master_portion` snapshot
- `loans.amount_disbursed += amount`

## B) Loan Disbursement (import/explicit portions variant)

Method: `postLoanDisbursementWithPortions(...)`  
Ledger source: `Loan`

- **Debit** `member_fund` by member portion (if > 0)
- **Debit** `master_fund` by master portion (if > 0)
- **Debit** `loan` account by total approved amount
- **Credit** `member_cash` by total approved amount

## C) Repayment – Cash collection leg

Method: `debitCashForRepayment(...)`  
Ledger source: `LoanInstallment`

- **Debit** `member_cash` by:
  - installment principal
  - plus late fee (if any)

## D) Repayment – Observer-posted settlement legs

Method: `postLoanRepayment(...)`  
Triggered by: `LoanInstallmentObserver` when status first becomes `paid`  
Ledger source: `LoanInstallment`

- **Credit** `master_fund` by installment principal
- **Credit** `member_fund` by installment principal
- **Credit** `loan` account by installment principal
- If late fee exists:
  - **Credit** `master_cash` by late fee

Also updates:

- `loan.repaid_to_master += installment principal`
- guarantor release check (`releaseGuarantorIfDue`)

## E) Guarantor default collection leg

Method: `debitGuarantorFundForDefault(...)`  
Ledger source: `LoanInstallment`

- **Debit** guarantor `member_fund` by installment principal

Also updates installment guarantor-paid marker in default path.

---

## Net Posting Patterns (Quick Reference)

## Normal installment repayment (not late)

- 1 debit + 3 credits:
  - debit member cash
  - credit master fund
  - credit member fund
  - credit loan account

## Late installment repayment

- 1 debit + 4 credits:
  - same as above
  - plus credit master cash for late fee

## Disbursement tranche

- 3 debits + 1 credit:
  - debit master fund
  - debit member fund
  - debit loan account
  - credit member cash

---

## Scheduled Command Workflow (Operations)

Defined in `routes/console.php`:

- `loans:notify` (monthly) — due notifications
- `loans:apply` (monthly) — apply repayments
- `loans:check-defaults` (monthly) — default and guarantor processing
- `fund:check-delinquency` (daily) — delinquency/suspension/liability transfer checks
- `loans:check-settlements` (daily) — complete loans when settlement conditions are met

---

## File Reference Index

- `app/Filament/Member/Resources/MyLoansResource.php`
- `app/Filament/Admin/Resources/LoanResource.php`
- `app/Filament/Admin/Resources/LoanResource/RelationManagers/InstallmentsRelationManager.php`
- `app/Services/LoanRepaymentService.php`
- `app/Services/LoanDefaultService.php`
- `app/Services/DelinquencyService.php`
- `app/Services/AccountingService.php`
- `app/Observers/LoanInstallmentObserver.php`
- `app/Models/Loan.php`
- `app/Models/LoanInstallment.php`
- `app/Models/LoanDisbursement.php`
- `app/Console/Commands/SendLoanRepaymentNotifications.php`
- `app/Console/Commands/ApplyLoanRepayments.php`
- `app/Console/Commands/CheckLoanDefaults.php`
- `app/Console/Commands/CheckDelinquency.php`
- `app/Console/Commands/CheckLoanSettlements.php`

