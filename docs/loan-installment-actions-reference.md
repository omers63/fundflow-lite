# Loan Installment Actions Reference

This note documents the last three clarifications around installment actions and repayment posting.

## 1) What does `Mark Paid` do?

In the **Admin > Loan > Installments** table, `Mark Paid` is a **manual status action**.

It currently:

- sets installment `status = paid`
- sets `paid_at = now()`

It does **not directly** debit cash or post repayment-ledger entries by itself.

## 2) What does `Post Repayment` do?

`Post Repayment` runs the **full repayment workflow** for the member's installment due in the **current open cycle**.

It performs:

- installment lookup for the open period
- late-fee calculation (if overdue)
- member cash sufficiency check
- cash debit posting
- installment update to paid
- accounting/ledger postings for repayment legs
- late counters update (if applicable)
- repayment notification

In short: `Post Repayment` is the operational/accounting flow; `Mark Paid` is a manual table action.

## 3) What ledger/fund entries are posted in repayment flow?

Repayment posting happens in two stages:

### A) Repayment service (before installment status change)

- **Debit** `Member Cash` by:
  - `installment amount`, plus
  - `late fee` (if any)

### B) Installment observer (when status becomes `paid`)

- **Credit** `Master Fund` by `installment amount`
- **Credit** `Member Fund` by `installment amount`
- **Credit** `Loan Account` by `installment amount` (reduces outstanding loan balance)
- If late fee exists:
  - **Credit** `Master Cash` by `late fee`

### Net pattern per installment

- **Not late:** `1 debit + 3 credits`
- **Late:** `1 debit + 4 credits` (extra late-fee credit to master cash)

Additionally, the loan field `repaid_to_master` is incremented by the installment principal amount.

