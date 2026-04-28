# FundFlow Reconciliation Process (End-to-End)

Version: 1.0  
Audience: Finance admins, technical admins, support engineers  
Scope: Full reconciliation of FundFlow virtual accounts, ledgers, imports, loans, contributions, and member-portal Post Funds flow.

---

## 1) Purpose

Reconciliation in FundFlow verifies that:

- stored account balances match ledger roll-forward math,
- expected mirror postings exist across master/member accounts,
- import pipelines are not silently stuck,
- contributions and loans are ledger-consistent,
- optional bank statement balances match book balances,
- and snapshots are captured for audit.

This process protects financial correctness before period close, reporting, and operational decisions.

---

## 2) Core Accounting Model

FundFlow uses virtual accounts:

- `master_cash`
- `master_fund`
- `member_cash` (per member)
- `member_fund` (per member)
- `loan` (per loan)

Every posting creates `account_transactions` rows. Reconciliation treats ledger lines as source of truth and checks whether persisted account balances reflect those lines.

---

## 3) Where Reconciliation Runs

### Admin UI

- Page: `Finance -> Reconciliation`
- Class: `app/Filament/Admin/Pages/ReconciliationPage.php`
- View: `resources/views/filament/admin/pages/reconciliation.blade.php`

Header actions:

- `Run now (real-time)`
- `Daily snapshot`
- `Monthly snapshot`

### CLI command

- Command: `php artisan fund:reconcile`
- Class: `app/Console/Commands/FundReconcileCommand.php`

Modes:

- `--realtime`
- `--daily`
- `--monthly`
- `--no-store` (summary only; no snapshot row)

### Scheduled automation

Registered in scheduler (see `routes/console.php`):

- daily reconciliation snapshot
- monthly reconciliation snapshot

---

## 4) Service and Data Flow

Main service:

- `app/Services/FinanceReconciliationService.php`

Primary methods:

- `buildReport(...)`: computes full reconciliation report
- `persistSnapshot(...)`: writes snapshot row
- `bankOptionsFromSettings()`: loads optional statement parameters from settings

Snapshots:

- Model: `app/Models/ReconciliationSnapshot.php`
- Stores summary + full JSON report payload for historical audit.

---

## 5) Reconciliation Checks (What Is Validated)

This section maps each check to intent and failure implications.

### 5.1 `ledger_balances` (Critical)

Compares each account's stored `balance` to computed:

- `sum(credits) - sum(debits)` from non-deleted ledger rows.

Failure means balance drift exists and should be treated as a hard accounting issue.

### 5.2 `global_trial` (Warning)

Compares total credits vs total debits globally.

This can warn (not always critical) because some operational flows may be intentionally one-sided over subsets.

### 5.3 `paired_control_totals` (Warning)

Compares:

- `master_cash` vs sum of `member_cash`
- `master_fund` vs sum of `member_fund`

Advisory only: some valid business flows can make strict parity differ (example: member-only cash debits for repayments, guarantor-specific fund debits).

### 5.4 `bank_statement_vs_book` (Warning/Critical/Skipped)

Optional check:

- compares declared statement balance against `master_cash` book balance.

Severity:

- warning by default if variance exists,
- critical if strict toggle enabled,
- skipped if no declared statement balance provided.

### 5.5 `contributions_ledger` (Critical or Warning)

Checks:

- each non-deleted `contributions` row has corresponding ledger lines,
- sum of contribution rows vs sum of `master_fund` credits sourced from contributions.

Missing postings -> critical. Sum mismatches -> warning.

### 5.6 `member_portal_posting_integrity` (Critical)

Covers the member portal "Post Funds" flow (`raw_data.source = member_portal_post` on bank transactions).

For each such bank transaction, validates:

- transaction is posted (`posted_at` exists),
- transaction type is `credit`,
- `member_id` exists,
- master cash ledger line exists and matches amount/type,
- member cash mirror ledger line exists for the same member.

Any failure is critical.

### 5.7 `active_loans_schedule_vs_ledger` (Warning)

For active loans:

- compares loan account outstanding vs installment schedule remaining amount.

### 5.8 `approved_loans_disbursement_vs_ledger` (Warning)

For approved loans with disbursement:

- if no installments yet, loan ledger should match `amount_disbursed`,
- otherwise compare to remaining installment schedule.

### 5.9 `orphan_loan_accounts` (Critical)

Detects loan-type accounts whose parent loan row no longer exists.

### 5.10 Pipeline Metrics (Warning)

Tracks unposted import rows:

- unposted bank transactions
- unposted SMS transactions

Backlog indicates operational pipeline lag.

---

## 6) Member Portal "Post Funds" and Reconciliation

Post Funds route:

1. Create bank transaction (`source = member_portal_post`)
2. Post to `master_cash` + `member_cash`
3. Apply dependent allocations as needed
4. Apply contribution or repayment per current-cycle business rules

Reconciliation coverage:

- cash movement integrity is enforced by `member_portal_posting_integrity`
- contribution postings are covered by `contributions_ledger`
- repayment/loan consistency is covered by loan schedule checks
- account-level integrity is covered by `ledger_balances`

This gives end-to-end coverage for the full Post Funds workflow across all account types.

---

## 7) Severity and Pass/Fail Rules

Verdict in report:

- `pass = true` only when `critical_issues = 0`
- warnings do not fail the report, but require review

Guideline:

- Critical -> investigate before closing books or major operations
- Warning -> track trend and evaluate business-context impact

---

## 8) Operating Procedure (Runbook)

### Daily

1. Open latest snapshot in Reconciliation page.
2. Confirm no critical issues.
3. Review warnings:
   - unposted imports
   - trial drift
   - control-total variance

### Before monthly close

1. Run realtime reconciliation manually.
2. Provide statement closing balance and date for bank-vs-book comparison.
3. Resolve critical issues.
4. Export JSON and PDF for archive.

### After bulk imports or data repair

1. Run realtime reconciliation immediately.
2. Verify:
   - `ledger_balances` mismatch count not increased
   - no new orphan or missing-posting issues
   - pipeline backlog trending down

---

## 9) Troubleshooting Playbook

### Case A: `ledger_balances` mismatch > 0

- Inspect `checks.ledger_balances.mismatches`
- Confirm affected account IDs and deltas
- Check for manual edits, incomplete reversals, partial source repairs

### Case B: Contribution missing ledger rows

- Use `checks.contributions_ledger.missing_ledger_sample`
- Re-run posting/repair process for affected contribution IDs

### Case C: Post Funds integrity issues

- Inspect `checks.member_portal_posting_integrity.issues`
- Typical causes:
  - unposted bank transaction
  - missing member cash mirror line
  - wrong transaction type

### Case D: Loan schedule mismatch warnings

- Compare loan ledger account to installment rows
- Validate disbursement sequence and repayment posting chronology

---

## 10) Data Inputs and Settings

Optional statement comparison settings:

- `reconciliation.bank_statement_balance`
- `reconciliation.bank_statement_date`
- `reconciliation.bank_variance_critical`

These can be supplied via UI per run or loaded from settings for scheduled runs.

---

## 11) Permissions

Relevant permissions (Filament Shield custom permissions):

- `reconciliation_view`
- `reconciliation_run`
- `reconciliation_export`

Ensure appropriate assignment for finance admins and auditors.

---

## 12) Outputs and Audit Artifacts

Each snapshot stores:

- mode + timestamps
- verdict and issue counts
- summary headline checks
- full machine-readable report payload

Download formats:

- JSON: full payload for forensic analysis
- PDF: executive-readable snapshot summary

Retention recommendation:

- keep monthly snapshots permanently,
- keep daily snapshots per internal policy (for example 12-24 months).

---

## 13) Validation Checklist (End-to-End)

Use this after feature changes impacting money movement:

- [ ] Realtime reconciliation run completes
- [ ] `member_portal_posting_integrity` is `ok`
- [ ] `contributions_ledger` has no missing postings
- [ ] No new critical issues introduced
- [ ] Loan checks show expected behavior for active/approved disbursed loans
- [ ] Pipeline backlogs are understood and tracked

---

## 14) Quick Command Reference

```bash
# Realtime run and store snapshot
php artisan fund:reconcile --realtime

# Realtime summary only (no DB snapshot)
php artisan fund:reconcile --realtime --no-store

# Daily mode
php artisan fund:reconcile --daily

# Monthly mode
php artisan fund:reconcile --monthly
```

---

## 15) Ownership

- Process owner: Finance Operations
- Technical owner: Application Engineering
- Review cadence: monthly, or immediately after accounting workflow changes

