# FundFlow — Administrator Operational Manual

**Version:** 1.0 | **Platform:** FundFlow Admin Portal | **Currency:** SAR (Saudi Riyal)

---

## Table of Contents

1. [System Overview](#1-system-overview)
2. [Accessing the Admin Portal](#2-accessing-the-admin-portal)
3. [Dashboard](#3-dashboard)
4. [Membership Management](#4-membership-management)
   - 4.1 Membership Applications
   - 4.2 Creating Members Directly
   - 4.3 Editing Members
   - 4.4 Viewing Member Profiles
5. [Contribution Management](#5-contribution-management)
   - 5.1 Contribution Cycle Logic
   - 5.2 Running a Contribution Cycle
   - 5.3 Manually Creating Contributions
   - 5.4 Late Contribution Tracking
6. [Loan Management](#6-loan-management)
   - 6.1 Loan Eligibility Rules
   - 6.2 Loan Application Review
   - 6.3 Approving a Loan
   - 6.4 Disbursing a Loan
   - 6.5 Loan Repayment Cycle
   - 6.6 Default Handling
   - 6.7 Early Settlement
   - 6.8 Loan Queue
7. [Banking & Transaction Import](#7-banking--transaction-import)
   - 7.1 Configuring Banks
   - 7.2 Import Templates (Bank Statements)
   - 7.3 Importing Bank Files
   - 7.4 Posting Transactions to Member Cash Accounts
   - 7.5 SMS Import
8. [Virtual Accounts & Ledger](#8-virtual-accounts--ledger)
9. [Parent–Dependent Relationships](#9-parentdependent-relationships)
10. [Monthly Statements](#10-monthly-statements)
11. [Settings](#11-settings)
    - 11.1 Loan Settings
    - 11.2 Loan Tiers
    - 11.3 Fund Tiers
12. [Scheduled Automation](#12-scheduled-automation)
13. [Notifications Reference](#13-notifications-reference)
14. [Artisan Commands Reference](#14-artisan-commands-reference)
15. [Financial Reconciliation Runbook](#15-financial-reconciliation-runbook)

---

## 1. System Overview

FundFlow is a member-funded cooperative finance platform. Its core functions are:

| Domain | Description |
|--------|-------------|
| **Membership** | Application processing, direct member creation, parent-dependent hierarchy |
| **Contributions** | Monthly contribution cycle: collect, apply, track late payments |
| **Loans** | Tiered loans from the master fund, queue management, repayment, defaults |
| **Banking** | Import bank/SMS statements to reconcile incoming cash transfers |
| **Accounting** | Five virtual account types maintain real-time balances per member and for the fund |
| **Statements** | Monthly per-member financial statements |

### Virtual Account Architecture

Every monetary operation in FundFlow posts double-entry ledger entries to one or more of five account types:

| Account Type | Scope | Purpose |
|---|---|---|
| `master_cash` | Fund-wide | Receives all incoming cash transfers from members |
| `master_fund` | Fund-wide | Tracks the fund's investable capital (grows with contributions and repayments, shrinks on loan disbursements) |
| `member_cash` | Per-member | Member's cash available for contributions and repayments |
| `member_fund` | Per-member | Member's proportional stake in the fund; grows with contributions and repayments |
| `loan` | Per-loan | Outstanding loan balance |

> **Key rule:** Cash never leaves the system unless explicitly disbursed. All contribution and repayment flows pass through the `member_cash` account first.

---

## 2. Accessing the Admin Portal

**URL:** `/admin`

Administrators log in with their email and password. Only users with the `admin` role can access this panel.

### Navigation Groups

| Group | Sections |
|---|---|
| **Membership** | Applications, Members |
| **Finance** | Contributions, Loans, Loan Queue, Contribution Cycle, Accounts |
| **Banking** | Banks, Import Templates, Import Sessions, Transactions, SMS Import |
| **Reports** | Monthly Statements |
| **Settings** | Loan Settings, Loan Tiers, Fund Tiers, Shield (Roles & Permissions) |

---

## 3. Dashboard

The admin dashboard shows a real-time statistics widget:

| Stat | Description |
|------|-------------|
| **Active Members** | Count of members with `status = active` |
| **Pending Applications** | Membership applications awaiting decision |
| **Total Fund** | Master fund account balance (SAR) |
| **Active Loans** | Count of loans with `status = active` |
| **Overdue Installments** | Total overdue installment count across all active loans |
| **Delinquent Members** | Members flagged as delinquent |

---

## 4. Membership Management

### 4.1 Membership Applications

**Navigation:** Membership → Applications

When a prospective member submits an application from the public-facing form, it appears here with status `pending`.

#### Reviewing an Application

1. Click **View** on the application row.
2. Review applicant details: name, email, phone, city, purpose, documents.
3. Choose an action:

**Approve:**
- Click **Approve** in the row actions.
- The system automatically:
  - Creates a `User` record with role `member`.
  - Generates a unique `member_number`.
  - Creates the `Member` record (status `active`).
  - Provisions `member_cash` and `member_fund` virtual accounts.
  - Sends `MembershipApprovedNotification` to the applicant.

**Reject:**
- Click **Reject**.
- Enter a rejection reason.
- The system sends `MembershipRejectedNotification` with the reason.

> **Note:** Approved applications cannot be reversed through the UI. If an error is made, locate the member record and update their status manually.

#### Filtering Applications

Use the status filter to view: **Pending**, **Approved**, or **Rejected** applications.

---

### 4.2 Creating Members Directly

**Navigation:** Membership → Members → New Member

Admins can create members directly, bypassing the application workflow.

**Required fields:**

| Section | Fields |
|---------|--------|
| Login Credentials | Name, Email, Phone, Password |
| Membership Details | Joined Date, Status |
| Contribution & Sponsorship | Monthly Contribution Amount (SAR 500–3,000 in steps of 500), Parent Member (optional) |

The system performs the same post-creation provisioning as application approval: member number generation, virtual account creation.

> **Restriction:** A member who already has dependents cannot be assigned as a dependent (the Parent Member field is disabled in their edit form).

---

### 4.3 Editing Members

**Navigation:** Members → Edit (pencil icon)

Editable fields include:
- Contact information
- Status (`active`, `inactive`, `delinquent`, `cancelled`)
- Monthly contribution amount
- Parent Member (Sponsor) — only visible if the member has no dependents

#### Contribution & Sponsorship Section

- **Monthly Contribution Amount:** Select from SAR 500 / 1,000 / 1,500 / 2,000 / 2,500 / 3,000.
- **Parent Member:** Only members who are not themselves dependents appear in this dropdown. The field is disabled if the member being edited has dependents of their own.

---

### 4.4 Viewing Member Profiles

**Navigation:** Members → View (eye icon)

The member view page includes four relation manager tabs:

| Tab | Contents |
|-----|----------|
| **Contributions** | All contributions with amount, period, payment method, late flag |
| **Loans** | All loans with status, amounts, tier |
| **Accounts** | Virtual accounts (cash, fund balances) |
| **Dependents** | Dependent members with allocation, cash balance; actions: Set Allocation, Fund Cash Account |

#### Dependents Tab Actions

**Set Allocation:** Opens a modal to change the dependent's `monthly_contribution_amount`. Both the parent and the admin can perform this.

**Fund Cash Account:** Transfers SAR from the parent's cash account to the dependent's cash account. Specify the amount and an optional note. The parent's cash balance must be sufficient; an error is shown if not.

---

## 5. Contribution Management

### 5.1 Contribution Cycle Logic

Contributions work on a **monthly cycle**:

| Date | Event |
|------|-------|
| 1st of Month M+1 | Notification sent to members for Month M contribution |
| 5th of Month M+1 | **Deadline** — contribution auto-applied from member cash accounts |
| After 5th | Contributions applied late; `is_late = true` on the record |

**Example:** June's contribution is due by **5 July**. The notification is sent on **1 July**. Auto-application runs on **5 July**.

**Members exempt from contributions** (automatically skipped):
- Members with an **active or approved loan** are exempt for the duration of the loan. Loan repayments replace contributions.
- Members who have **already contributed** for the period.

**Contribution amount** = the member's configured `monthly_contribution_amount` (SAR 500–3,000).

---

### 5.2 Running a Contribution Cycle

**Navigation:** Finance → Contribution Cycle

This page shows all active members who have **not yet contributed** for the current open period, along with:
- Required amount
- Current cash balance
- Shortfall (if any)
- Parent member (if applicable)

#### Header Actions

**Send Due Notifications:**
1. Click **Send Due Notifications**.
2. Select the month and year (defaults to the previous calendar month).
3. Click **Send**. The system queues `ContributionDueNotification` to each eligible member via email, database, SMS, and WhatsApp.

**Run Contribution Cycle:**
1. Click **Run Contribution Cycle**.
2. Select the month and year.
3. Click **Run**. The system:
   - Iterates all active members not exempt from contributions.
   - Checks each member's `member_cash` balance.
   - If sufficient: debits cash, credits master and member fund accounts, creates a `Contribution` record, sends `ContributionAppliedNotification`.
   - If insufficient: records the member in the "insufficient" result set (shown in a summary notification).

#### Per-Row Action

**Apply Now:** Applies the contribution for a single member immediately using the current open period.

---

### 5.3 Manually Creating Contributions

**Navigation:** Finance → Contributions → New

Use this when a member pays by a method not handled by the automated cycle (e.g., cheque, cash deposit).

| Field | Notes |
|-------|-------|
| Member | Select from active members |
| Amount | SAR amount |
| Month / Year | The contribution period |
| Payment Method | `cash_account`, `bank_transfer`, `cheque`, etc. |
| Reference Number | Optional external reference |
| Notes | Free text |
| Paid At | Timestamp |

> Saving a contribution automatically posts the accounting entry (credits master fund and member fund).

---

### 5.4 Late Contribution Tracking

A contribution is marked `is_late = true` if it is applied **after the 5th of the month following the contribution period**.

Each member record stores:
- `late_contributions_count` — running count of late contributions
- `late_contributions_amount` — cumulative SAR of late contributions

These fields appear in the Members table as columns and are updated automatically when the contribution cycle is run late.

---

## 6. Loan Management

### 6.1 Loan Eligibility Rules

A member may apply for a loan only when **all** of the following conditions are met:

| Condition | Default | Configurable? |
|-----------|---------|---------------|
| Status is **active** | Always required | No |
| Membership duration ≥ **12 months** from `joined_at` | 12 months | Yes — Loan Settings |
| Fund account balance ≥ **SAR 6,000** | SAR 6,000 | Yes — Loan Settings |
| No **overdue loan installments** | Always required | No |

**Maximum loan amount:** `2 × member's fund account balance` (multiplier configurable in Loan Settings).

**Loan amount range** is bounded by the applicable **Loan Tier** (see §11.2).

---

### 6.2 Loan Application Review

**Navigation:** Finance → Loans

New applications appear with status **Pending**. A badge on the nav item shows the pending count.

**Table columns:** Queue #, Loan Tier, Member #, Member Name, Requested, Approved, Months, Status, Late Repayments #, Applied Date.

**Filters:** Status, Loan Tier.

---

### 6.3 Approving a Loan

**Row action:** Approve (visible only when status = `pending`)

1. Click **Approve**.
2. Complete the approval form:

| Field | Description |
|-------|-------------|
| Approved Amount (SAR) | May differ from requested; must be within a loan tier range |
| Number of Installments | 1–36 months |
| Assign to Fund Tier | Select an active fund tier; available SAR is shown in the dropdown |

3. Click **Approve**.

The system:
- Sets `status = approved`.
- Auto-detects the **Loan Tier** from the approved amount.
- Assigns a **queue position** within the selected Fund Tier.
- Snapshots the current `settlement_threshold` (from Loan Settings) onto the loan.
- Sends `LoanApprovedNotification` to the member.

---

### 6.4 Disbursing a Loan

**Row action:** Disburse (visible only when status = `approved`)

1. Click **Disburse**.
2. A confirmation modal shows the member's fund balance and master fund balance.
3. Confirm.

The system:
- Calculates the **disbursement split**:
  - `member_portion = min(member.fundAccount.balance, amount_approved)`
  - `master_portion = amount_approved − member_portion`
- Debits the member's fund account by `member_portion`.
- Debits the master fund account by `master_portion`.
- Creates all installment records (amount ≥ tier's minimum monthly installment).
- Computes **exemption** and **first repayment** period (see below).
- Sets `status = active`.
- Sends `LoanDisbursedNotification`.

#### Contribution Exemption & First Repayment Calculation

| Disbursement Date | Exempted Contribution Month | First Repayment Month |
|---|---|---|
| On or before the 5th of Month M+1 | Month M (currently open) | Month M+1 (current month) |
| After the 5th of Month M+1 | Month M+1 | Month M+2 |

**Example A:** Loan disbursed on **3 July** (before the July 5 deadline for June contributions):
- June's contribution is **exempt**.
- First repayment cycle: **July** (due by 5 August).

**Example B:** Loan disbursed on **10 July** (after the July 5 deadline):
- July's contribution is **exempt**.
- First repayment cycle: **August** (due by 5 September).

---

### 6.5 Loan Repayment Cycle

Repayments follow the same calendar as contributions:

| Date | Event |
|------|-------|
| 1st of Month M+1 | `LoanRepaymentDueNotification` sent to borrower |
| 5th of Month M+1 | Repayment auto-applied from member's cash account |
| After 5th | Installment flagged `is_late = true` |

**Repayment accounting for each installment:**
- **Debit:** `member_cash` (full installment amount)
- **Credit:** `master_fund` (full installment amount)
- **Credit:** `member_fund` (full installment amount)

> Both master and member fund are credited on every repayment. The member's fund balance grows with each installment, which is required to satisfy the settlement condition.

**`repaid_to_master` tracking:** Each repayment increments `repaid_to_master` on the loan. When `repaid_to_master ≥ master_portion`, the guarantor is **automatically released**.

**Manually triggering a repayment cycle:**
```
php artisan loans:apply {month} {year}
```

---

### 6.6 Default Handling

The system runs `loans:check-defaults` on the **6th of each month**.

**Default count** = `late_repayment_count` on the loan (cumulative, consecutive or not).

| Default Count | Action |
|---|---|
| ≤ Grace cycles (default: 2) | `LoanDefaultWarningNotification` sent to borrower |
| > Grace cycles | `LoanDefaultGuarantorNotification` sent to guarantor; installment debited from **guarantor's fund account** |
| Every subsequent missed payment | Same: guarantor's fund debited + notified |

**Guarantor rules:**
- The guarantor is released when `repaid_to_master ≥ master_portion` — i.e., the fund's capital is fully returned, regardless of the 16% settlement threshold.
- If the guarantor's membership is cancelled or they withdraw, **their guarantee continues** until the borrower fully settles the fund's portion.

The grace cycle count is configurable in **Loan Settings** (default: 2).

---

### 6.7 Early Settlement

**Row action:** Early Settle (visible when status = `active`)

1. Click **Early Settle**.
2. The confirmation modal shows the remaining balance.
3. Confirm.

The system:
- Iterates all `pending`/`overdue` installments.
- Debits the member's cash account for each.
- Marks each installment `paid`.
- Sets loan `status = early_settled` and `settled_at = now()`.
- Sends `LoanEarlySettledNotification`.

> **Important:** Ensure the member has sufficient cash balance before triggering early settlement. The system does not check balance before attempting the debit chain.

#### Automatic Loan Settlement (Daily)

`loans:check-settlements` runs daily at 10:00. A loan is automatically marked `completed` when **both** conditions are met:

1. `repaid_to_master ≥ master_portion` — fund's principal fully returned.
2. `member.fundAccount.balance ≥ settlement_threshold × amount_approved` — member's fund has recovered to the configured threshold (default **16%** of the original loan).

When settled, `LoanSettledNotification` is sent and the member resumes the contribution cycle.

---

### 6.8 Loan Queue

**Navigation:** Finance → Loan Queue

Displays **all active fund tiers** with their pending and approved loans ordered by queue position (FIFO within each tier).

**Columns:** Position, Member, Loan Tier, Amount (SAR), Status, Applied Date.

Each tier header shows:
- Allocation percentage
- Available funds (SAR) = `master_fund_balance × (percentage ÷ 100) − active_loan_exposure`

> The queue is informational. Disbursement is performed from the **Loans** resource (Finance → Loans → Disburse action).

---

## 7. Banking & Transaction Import

FundFlow supports importing bank statement files (CSV/Excel) and SMS transaction files to reconcile incoming cash with member accounts.

### 7.1 Configuring Banks

**Navigation:** Banking → Banks

Create a bank record for each institution the fund uses:

| Field | Description |
|-------|-------------|
| Name | e.g., "Al Rajhi Bank" |
| Code | Short code, e.g., "RAJHI" |
| SWIFT | SWIFT/BIC code |
| Account Number | Fund's account number at this bank |
| Active | Toggle |

---

### 7.2 Import Templates (Bank Statements)

**Navigation:** Banking → Import Templates

Templates define how a CSV export from a specific bank is parsed. Configure one template per bank format.

**Key template settings (multi-tab form):**

| Setting | Description |
|---------|-------------|
| Delimiter | CSV delimiter (comma, semicolon, tab, pipe) |
| Encoding | File encoding (UTF-8, Windows-1256, etc.) |
| Has Header Row | Whether the first row is a header |
| Date Column / Format | Column index and format string (`d/m/Y`, `Y-m-d`, etc.) |
| Amount Column | Single column, or split Debit/Credit columns |
| Type Column / Values | Column and text values that indicate credit vs debit |
| Reference Column | Column containing transaction reference |
| Description Column | Narrative column |
| Duplicate Detection | Rules: same date + amount, or + reference |

---

### 7.3 Importing Bank Files

**Navigation:** Banking → Import Sessions → Import CSV

1. Select the **Bank** and **Template**.
2. Upload the CSV/Excel file.
3. The system parses the file and creates `BankTransaction` records.
4. The session record shows: total rows, imported, duplicates detected, errors.

If a session partially fails, use **Re-import** to retry.

---

### 7.4 Posting Transactions to Member Cash Accounts

**Navigation:** Banking → Transactions

After import, transactions need to be **posted** to member cash accounts to make funds available for contributions and loan repayments.

**Per-row action — Post to Cash:**
1. Find the transaction row.
2. Click **Post to Cash**.
3. Select the member.
4. Confirm.

The system:
- Credits `master_cash` (fund's incoming cash).
- Credits `member_cash` (member's available balance).
- Marks the transaction as `posted`.

**Bulk actions:**
- **Post Selected:** Post multiple selected transactions to individual members (one member per transaction).
- **Bulk Post to One Member:** Post all selected transactions to a single member (e.g., multiple transfers from the same person).

> **Duplicate detection:** Transactions flagged as duplicates show a warning badge. Review these before posting to avoid double-crediting.

---

### 7.5 SMS Import

**Navigation:** Banking → SMS Import Templates / Sessions / Transactions

Follows the same pattern as bank import but for SMS notification files:

1. Configure an **SMS Import Template** (regex-based amount parsing, column mapping).
2. Create an **SMS Import Session** by uploading a file.
3. Review **SMS Transactions** and post to member cash accounts.

The SMS import can optionally auto-match transactions to members based on phone number or member number patterns in the SMS text.

---

## 8. Virtual Accounts & Ledger

**Navigation:** Finance → Accounts (labelled "Virtual Accounts")

Each row represents one virtual account. Types:

| Type | Who | Purpose |
|------|-----|---------|
| `master_cash` | Fund | Aggregate incoming cash |
| `master_fund` | Fund | Investable capital of the fund |
| `member_cash` | Each member | Cash available for contributions/repayments |
| `member_fund` | Each member | Member's stake in the fund |
| `loan` | Each active loan | Outstanding loan balance |

**Viewing the ledger:** Click **View** (Ledger) on any account to see its full transaction history: date, description, debit, credit, running balance.

**Filtering:** Filter by account type to see all member cash accounts, all fund accounts, etc.

> Balances are updated in real time with every accounting operation. They should never be edited manually — all changes must flow through the proper service methods.

---

## 9. Parent–Dependent Relationships

A member hierarchy allows one member (the **parent** / sponsor) to financially support one or more **dependents**.

### Rules

| Rule | Detail |
|------|--------|
| A member can have **one parent** | Set via `parent_id` on the member |
| A member can have **multiple dependents** | Via `dependents` relationship |
| A **dependent cannot be a parent** | Enforced in both admin and member portal dropdowns |
| A **parent cannot become a dependent** | If a member has dependents, the Parent field in their edit form is disabled |

### Parent Responsibilities

1. **Fund the dependent's cash account** (via Dependents tab on the member view page or via My Dependents in the member portal).
2. **Set the dependent's contribution allocation** (via the same tab or portal).
3. Parent can also **fund the dependent's cash account for loan repayments** — the same `fundDependentCashAccount` mechanism applies.

### Admin Workflow

1. Open the parent's member view page.
2. Click the **Dependents** tab.
3. Use **Set Allocation** to change a dependent's monthly contribution amount.
4. Use **Fund Cash Account** to transfer SAR from the parent's cash account to the dependent's.

---

## 10. Monthly Statements

**Navigation:** Reports → Monthly Statements

Monthly statements provide a financial summary per member per calendar month.

| Column | Description |
|--------|-------------|
| Member # | Member identifier |
| Period | `YYYY-MM` |
| Opening Balance | Fund balance at start of period |
| Contributions | Contribution credits in the period |
| Repayments | Loan repayment credits in the period |
| Closing Balance | Fund balance at end of period |
| Generated At | When the statement was created |

**Generating statements:**
- **Header action — Generate This Month:** Creates statements for all active members for the current month. Existing statements for the period are updated.
- Statements can be filtered by period (month/year).

**Member access:** Members can view and download PDF statements from their portal.

---

## 11. Settings

### 11.1 Loan Settings

**Navigation:** Settings → Loan Settings

Click **Save Settings** to open a modal with the following configurable rules:

#### Eligibility Rules

| Setting | Default | Description |
|---------|---------|-------------|
| Eligibility Period | 12 months | How long a member must have been active before applying for a loan |
| Min Fund Balance | SAR 6,000 | Minimum fund account balance required to be eligible |
| Max Borrow Multiplier | 2 | Max loan = N × fund balance (e.g., 2 = up to double the fund balance) |

#### Repayment Rules

| Setting | Default | Description |
|---------|---------|-------------|
| Settlement Threshold | 16% | Loan is settled when master portion is repaid AND member's fund balance ≥ this % of original loan |
| Default Grace Cycles | 2 | Number of missed repayment cycles that generate warnings before the guarantor is held liable on the 3rd+ |

---

### 11.2 Loan Tiers

**Navigation:** Settings → Loan Tiers

Loan tiers bracket loan amounts and define the minimum monthly installment for each bracket.

**Default tiers:**

| Tier | From (SAR) | To (SAR) | Min Installment/mo (SAR) |
|------|-----------|---------|--------------------------|
| 1 | 1,000 | 30,000 | 1,000 |
| 2 | 31,000 | 60,000 | 1,500 |
| 3 | 61,000 | 90,000 | 2,000 |
| 4 | 91,000 | 120,000 | 2,500 |
| 5 | 121,000 | 150,000 | 3,000 |
| 6 | 151,000 | 180,000 | 3,500 |
| 7 | 181,000 | 210,000 | 4,000 |
| 8 | 211,000 | 240,000 | 4,500 |
| 9 | 241,000 | 270,000 | 5,000 |
| 10 | 271,000 | 300,000 | 5,500 |

**Admin actions:** Create, edit, delete tiers. The tier is auto-detected from the approved loan amount on approval.

> **Warning:** Changing tier ranges after loans are active does not retroactively affect existing loans.

---

### 11.3 Fund Tiers

**Navigation:** Settings → Fund Tiers

Fund tiers allocate a percentage of the master fund balance to each loan tier's queue.

**Default fund tiers:**

| Fund Tier | Linked Loan Tier | Allocation |
|-----------|-----------------|------------|
| 0 — Emergency | (standalone) | 100% |
| 2 | Loan Tier 2 | 100% |
| 3–10 | Loan Tier 3–10 | 100% each |

**Live-computed columns (shown in the table):**

| Column | Formula |
|--------|---------|
| Allocated (SAR) | `master_fund_balance × (percentage ÷ 100)` |
| Active Loans (SAR) | Sum of `amount_approved` for active/approved loans in this tier |
| Available (SAR) | `Allocated − Active Loans exposure` |
| Active Loans # | Count of active/approved loans in this tier |

> Setting a fund tier's percentage below 100% caps how much of the master fund can be committed to that loan tier. At 100% (default), there is no cap — the full fund is available.

---

## 12. Scheduled Automation

FundFlow uses Laravel's task scheduler. The server's cron must be configured to run:
```
* * * * * php /path/to/fundflow/artisan schedule:run >> /dev/null 2>&1
```

**Scheduled tasks:**

| Schedule | Command | Action |
|----------|---------|--------|
| Daily 08:00 | `fund:check-delinquency` | Flags members as delinquent based on overdue criteria |
| 1st of month 09:00 | `contributions:notify` | Sends contribution due notifications for previous month |
| 5th of month 09:00 | `contributions:apply` | Auto-applies contributions from member cash accounts |
| 1st of month 09:30 | `loans:notify` | Sends loan repayment due notifications |
| 5th of month 09:30 | `loans:apply` | Auto-applies loan repayment installments |
| 6th of month 08:00 | `loans:check-defaults` | Processes missed repayments: warn borrowers, debit guarantors |
| Daily 10:00 | `loans:check-settlements` | Marks loans as completed when settlement conditions are met |

---

## 13. Notifications Reference

All notifications are delivered via available channels: **email**, **in-app database notification**, **SMS** (Twilio), and **WhatsApp**.

| Notification | Trigger | Recipient |
|---|---|---|
| `MembershipApprovedNotification` | Application approved | Applicant |
| `MembershipRejectedNotification` | Application rejected | Applicant |
| `ContributionDueNotification` | 1st of month (or manual) | Each active non-exempt member |
| `ContributionAppliedNotification` | Contribution cycle applied | Member |
| `LoanSubmittedNotification` | Member submits loan application | Member |
| `LoanApprovedNotification` | Admin approves loan | Member |
| `LoanDisbursedNotification` | Admin disburses loan | Member |
| `LoanCancelledNotification` | Loan cancelled (member or admin) | Member |
| `LoanRepaymentDueNotification` | 1st of month (or manual) | Borrower |
| `LoanRepaymentAppliedNotification` | Repayment applied (account statement) | Borrower |
| `LoanDefaultWarningNotification` | Default count ≤ grace cycles | Borrower |
| `LoanDefaultGuarantorNotification` | Default count > grace cycles | Guarantor |
| `LoanSettledNotification` | Loan auto-settled (daily check) | Borrower |
| `LoanEarlySettledNotification` | Admin triggers early settlement | Borrower |
| `DelinquencyAlertNotification` | Member flagged delinquent | Member |

---

## 14. Artisan Commands Reference

All commands can be run manually for a specific period or triggered by the scheduler.

```bash
# Contribution cycle
php artisan contributions:notify {month?} {year?}
php artisan contributions:apply  {month?} {year?}

# Loan repayment cycle
php artisan loans:notify         {month?} {year?}
php artisan loans:apply          {month?} {year?}

# Default & settlement checks
php artisan loans:check-defaults
php artisan loans:check-settlements

# Delinquency
php artisan fund:check-delinquency

# Cache management (after config changes)
php artisan optimize:clear
```

**Period arguments:** `month` and `year` default to the **previous calendar month** if omitted. Pass explicit values to reprocess historical periods:

```bash
# Reprocess March 2026 loan repayments
php artisan loans:apply 3 2026
```

> **Idempotency:** Applying contributions or repayments for a period that already has records for a member will skip that member — safe to run multiple times.

---

## 15. Financial Reconciliation Runbook

For the complete reconciliation process (checks, severities, run procedures, troubleshooting, and Post Funds integrity coverage), see:

- `docs/reconciliation-process.md`

---

*End of FundFlow Administrator Operational Manual*
