# Fiscal Year End Closing and Archive Design

## Goals

- Close a fiscal year with traceable, repeatable workflow.
- Move fiscal-year scoped data to a separate archive database.
- Verify integrity before declaring closure complete.
- Support safe restore later without destructive shortcuts.

## Data Model

### `fiscal_years`

- Defines fiscal windows and lifecycle status:
  - `open` -> `closing` -> `closed`
  - `closed` -> `restoring` -> `open` (when restore is run)
- Stores close metadata and actor.
- **`archive_database_path`** (relative to `base_path()`): SQLite file for this fiscal year, e.g. `database/archives/fy_1_fy2026.sqlite`. Set when executing close without a legacy `--archive` override.
- **`purged_primary_at` / `purge_metadata`**: populated when primary fact rows for that year are removed after a successful archive (optional step).

### `fiscal_year_account_snapshots`

- One row per (`fiscal_year_id`, `account_id`) when primary purge runs.
- **`closing_balance`**: `accounts.balance` immediately before fact deletion (audit / “opening” reference for the boundary).
- After purge, **`accounts.balance`** is reconciled from remaining `account_transactions` (credits minus debits).

### `fiscal_year_closures`

- One row per close/restore/dry-run action.
- Captures:
  - actor and timestamps
  - archive connection + batch id
  - per-table row counts
  - pass/fail outcome and error details

## Archive Plan (current implementation)

### Dimension rows (dependency order before facts)

Copied by **referenced id**, not only by fiscal dates (members/users may predate the year; loans may be touched via installments whose `applied_at` is outside the window):

1. `banks`
2. `bank_import_templates` (references `banks`)
3. `sms_import_templates` (references `banks` optionally)
4. `loan_tiers`, `fund_tiers`
5. `users` (posters/approvers, member `user_id`, import-session actors)
6. `members` (includes `parent_id` ancestry and loan guarantors)
7. `loans` (all loans referenced by the slice below)
8. `accounts` (ids from postings plus member/tied loan portfolios)
9. `bank_import_sessions`, `sms_import_sessions`

### Facts (FK-safe order among themselves)

Scoped primarily by fiscal dates on:

- `contributions` (`paid_at`)
- `loan_installments` (`due_date`)
- `loan_disbursements` (`disbursed_at`, plus disbursements linked from archived bank rows)
- `account_transactions` (`transacted_at`)
- `monthly_statements` (`generated_at`)
- `reconciliation_snapshots` (`as_of`)
- `bank_transactions` (rows in-window plus `duplicate_of_id` ancestor chain)
- `sms_transactions` (same duplicate expansion)
- `member_subscription_fees` (`paid_at`, must follow `account_transactions` when FK present)

Exports / Excel reuse `archiveTables()`, which adds a **`loans` sheet**: same referenced-loan id set as archive (not `applied_at` alone).

Bulk copy still runs with foreign keys temporarily relaxed on the archive connection (`withoutForeignKeyConstraints`) as a safeguard.

## Per–fiscal-year SQLite archive (default)

- **Default behaviour** (Filament and `fiscal:close` without `--archive`): one SQLite file per fiscal year under `database/archives/` (configurable via `config/fundflow.php` / `ARCHIVE_FY_DATABASE_SUBDIR`).
- Laravel connection name pattern: `archive_fy_{id}` (registered at runtime).
- On first use, the file is created empty and **`php artisan migrate --database=archive_fy_{id}`** runs automatically (full app schema on that file).
- `fiscal_years.archive_database_path` stores the relative path for restore and purge.

**Legacy single archive connection** (optional): pass `--archive=archive` to `fiscal:close` / `fiscal:restore` to target the static `archive` entry in `config/database.php`. **Primary purge is not supported** with that mode (the app requires a dedicated file per closed year for purge safety).

## Primary purge (after close)

- Optionally runs in the same workflow as close (Filament checkbox or `fiscal:close --execute --purge`) or later via Filament / `fiscal:purge {year} --force`.
- Preconditions: fiscal year **`closed`**, **`archive_database_path`** set, archive file exists, **`purged_primary_at` null**.
- Steps (single primary DB transaction): write `fiscal_year_account_snapshots` from current `accounts.balance`, delete fact rows in **reverse FK order** (same slice as close), set `purged_primary_at`, then **recalculate every `accounts.balance`** from remaining non–soft-deleted `account_transactions`.
- **Does not** delete dimension rows (`users`, `members`, `accounts`, `loans`, etc.) from primary.

## Runtime Workflow

### Dry run

- Registers the target archive connection (per-FY SQLite or legacy override).
- Runs migrations on per-FY SQLite if needed.
- Confirms archive DB has all required tables.
- Reports source and archive counts by table.

### Close

1. Acquire fiscal-year close lock.
2. Set FY status to `closing`.
3. (Default) Persist `archive_database_path` when missing, then ensure archive file + schema.
4. Resolve referenced ids (facts + FK parents + duplicate/import chains).
5. Copy dimension tables in FK order, then fact tables (`insertOrIgnore`, chunked).
6. Verify counts for each **fact** table (`source slice` vs `archive slice`; bank/sms use id sets).
7. Mark FY as `closed` and persist closure metadata (including archive path when using per-FY files).
8. Optional: **purge primary** (see above).

### Restore

1. Acquire restore lock.
2. Set FY status to `restoring`.
3. Register archive connection from **`archive_database_path`**, or fall back to legacy `archive` if the path is unset.
4. Pull archived **fact** rows (`upsert` by `id`) in the same order (extended bank/sms/disbursement ids are recomputed from the archive DB). Dimension tables on primary are **not** overwritten.
5. Recalculate all **`accounts.balance`** from the ledger on primary.
6. Mark FY back to `open`.

## Configuration

`config/fundflow.php`:

```env
# Calendar year for the first FY row (e.g. 2014 → FY2014)
FISCAL_YEAR_INITIAL=2014

# true: FiscalYearSeeder creates every FY from initial through calendar now (prior years synthetic-closed).
# false: only one FY row (open) at FISCAL_YEAR_INITIAL.
FISCAL_YEAR_SEED_THROUGH_CURRENT=true

# Subdirectory under database_path(), default "archives" → database/archives/*.sqlite
ARCHIVE_FY_DATABASE_SUBDIR=archives
```

Seeded historical years marked `closed` are **ledger placeholders only** — they did not run the close/archive workflow. Use real closes when you migrate live data into the system.

`config/database.php` still defines a static **`archive`** connection for backward compatibility (`ARCHIVE_DB_*` in `.env`).

## Commands

Dry-run close (creates/opens per-FY archive under `database/archives/`):

```bash
php artisan fiscal:close 2026
```

Execute close (per-FY file):

```bash
php artisan fiscal:close 2026 --execute --user-id=1
```

Close **and purge** primary facts after a verified archive:

```bash
php artisan fiscal:close 2026 --execute --purge --user-id=1
```

Purge primary only for an already-closed year:

```bash
php artisan fiscal:purge 2026 --force
```

Legacy single-connection close / restore:

```bash
php artisan fiscal:close 2026 --execute --archive=archive --user-id=1
php artisan fiscal:restore 2026 --archive=archive --user-id=1
```

Restore using `archive_database_path` on the fiscal year (omit `--archive`):

```bash
php artisan fiscal:restore 2026 --user-id=1
```

## Operational Notes / Next Hardening Steps

- Per-FY SQLite: backup `database/archives/fy_*` files alongside primary `database.sqlite` (or your MySQL dumps).
- Add pre-close guards: unposted imports, pending reconciliation, open workflow checks.
- Add immutable post-close write protections for closed-year records.
- Optional: offload old archive files to cold storage while keeping hashes in closure metadata.

