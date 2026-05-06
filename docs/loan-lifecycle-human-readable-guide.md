# Loan Lifecycle Guide (Human-Readable)

This guide explains how loans move through FundFlow in plain language, from request to closure.

It is written for operations, finance, and admin users who need to understand behavior and outcomes without reading technical code.

---

## Big Picture

A loan usually passes through these stages:

1. **Requested** (member or admin creates request)
2. **Approved** (admin accepts and sets final terms)
3. **Disbursed** (money is released, possibly in parts)
4. **Active repayment** (monthly installments are paid)
5. **Completed** or **Early settled**

If repayments are missed, the system moves through delinquency/default controls and may charge the guarantor.

---

## 1) Request Stage

At request time, the system captures:

- who is requesting
- requested amount
- purpose
- guarantor and witnesses
- whether it is emergency
- whether one-cycle grace is enabled before first installment

### What this means operationally

- The loan is **not funded yet**.
- It is waiting for admin decision.
- No repayment schedule is active yet.

---

## 2) Approval Stage

An admin reviews and approves or rejects the request.

When approved, admin confirms:

- approved amount
- approval date (can be overridden)
- emergency flag
- grace-cycle option

The system also assigns:

- loan tier
- fund tier
- expected installment count

### What this means operationally

- Loan is approved for disbursement.
- Still no repayment collection until disbursement completes.

---

## 3) Rejection or Cancellation Stage

If rejected:

- loan becomes **Rejected**
- rejection reason is recorded

If cancelled:

- loan becomes **Cancelled**
- cancellation reason is recorded (if provided)

### What this means operationally

- Loan process ends.
- No disbursement, no repayment schedule.

---

## 4) Disbursement Stage

Disbursement can be:

- **partial** (one or more portions)
- **full** (final portion completes approved amount)

Admin can override disbursement date per posting.

### Key behavior

- As long as total released amount is below approved amount, loan remains in approved state.
- When final amount is released, loan becomes **Active** and repayment schedule is created.

### Grace-cycle impact

- If grace is enabled: first installment starts one cycle later.
- If grace is disabled: first installment starts in the immediate eligible cycle after disbursement logic.

---

## 5) Repayment Stage

Repayments are tied to contribution cycles and can run automatically through scheduled jobs or one-click actions.

For each due installment:

- the system checks cash availability
- applies late fee rules if overdue
- posts repayment
- marks installment paid

### Important distinction in UI actions

- **Post Repayment** = full financial posting flow
- **Mark Paid** = manual status update action (used administratively)

Operationally, use **Post Repayment** when you want full accounting movement and policy checks.

---

## 6) Delinquency Stage

If due installments remain unpaid after deadline:

- installment status changes to overdue
- member delinquency checks run
- member may be suspended under policy thresholds

This protects fund discipline and ensures consistent enforcement.

---

## 7) Guarantor Liability Transfer and Default Handling

When borrower delinquency reaches policy thresholds:

- liability can be transferred to guarantor context
- default processing evaluates overdue installments
- after grace/warning rules, guarantor may be charged

### What this means operationally

- Guarantor is the safety backstop if borrower does not pay.
- System records that installment was handled via guarantor path.

---

## 8) Loan Completion Paths

A loan can end in two clean ways:

## A) Completed

Regular repayment conditions are fully met.

## B) Early settled

Borrower pays all remaining required amounts in one early-settlement action.

In both cases, loan is closed and no further installments are due.

---

## Accounting Meaning (Business View)

At a high level:

- **Disbursement** releases value to borrower cash and tracks outstanding loan obligation.
- **Repayment** reduces borrower cash, restores fund positions, and reduces outstanding loan balance.
- **Late fees** are tracked separately and credited to master cash policy bucket.
- **Default/guarantor events** shift repayment burden when borrower fails policy obligations.

You can read the technical debit/credit matrix in:

- `docs/loan-lifecycle-workflows-and-accounting.md`

---

## Operational Tips

- Use approval/disbursement date overrides carefully for backdated or corrective postings.
- Confirm grace-cycle option at approval time; it affects first installment timing.
- Prefer repayment actions that run full posting logic for accounting integrity.
- Monitor overdue and delinquency dashboards regularly to act before defaults escalate.
- Keep guarantor and witness information accurate for enforceability.

