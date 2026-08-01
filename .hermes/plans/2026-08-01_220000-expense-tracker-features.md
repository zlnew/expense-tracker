# Expense Tracker — Feature Roadmap (TK-0004)

**Status:** Plan — F1–F4 in progress, F5–F11 backlog
**Repo:** `repo/expense-tracker` · **Branch:** `feature/tk-0004-features`
**Date:** 2026-08-01 · **Owner:** Maul

## Scope (all 11, agreed with Maul)

Ordered by value; F1–F4 = current implementation batch, F5–F11 = backlog.

| # | Feature | Effort | Notes |
|---|---------|--------|-------|
| F1 | Recurring transactions | medium | auto-create/remind recurring income+expense (salary, rent, subs) |
| F2 | Budget rollover | small-medium | unused budget carries into next cycle (YNAB-style) |
| F3 | Overspend alerts | small | Discord webhook at 80% / 100% of a budget item |
| F4 | CSV export | small | transactions in date range → CSV for Excel/tax |
| F5 | Savings goals | medium | target + progress, optional monthly set-aside |
| F6 | Bank statement import | medium | CSV import w/ category matching (start: 1 bank format) |
| F7 | Split transactions | small-medium | one transaction across multiple categories |
| F8 | Search | small | full-text search + filters in transaction list |
| F9 | Budget templates | tiny | "copy last month's budget" |
| F10 | Receipt photos | medium | attach image to a transaction |
| F11 | Year-over-year reports | medium | compare same month across years |

## F1 — Recurring transactions

- Migration: `recurring_transactions` (user_id, type, balance_id, category_id,
  amount, description, frequency daily/weekly/monthly/yearly, start_date,
  end_date nullable, next_run_date, is_active).
- `RecurringTransaction` model + factory.
- `ProcessRecurringTransactions` action: finds due (next_run_date <= today,
  active), creates the real transaction via `SaveTransaction`, advances
  `next_run_date` by the frequency (no-overflow month math), deactivates past
  `end_date`.
- `recurring:process` artisan command + scheduler entry; also a cheap due-check
  when the dashboard loads (personal scale; no cron guaranteed on host).
- UI: `RecurringList` page (list + inline create/edit dialog, mobile-first),
  resource routes, ownership middleware included.

## F2 — Budget rollover

- Migration: `budgets.carry_over` boolean (default false).
- `BudgetRollover` support: leftovers(category → planned − spent from previous
  active budget cycle) helper.
- `SaveBudget`: when creating a NEW budget with carry_over=true, add previous
  cycle's unused (>=0) to the matching new item's planned_amount.
- UI: toggle in BudgetCreate; note in BudgetDetail when carry-over is on.

## F3 — Overspend alerts (Discord)

- Migration: `users.discord_webhook_url` nullable; `budget_items.alert_80_sent`,
  `alert_100_sent` booleans (reset naturally per budget).
- `CheckBudgetAlerts` action: on expense save, find affected budget items,
  compute spent vs planned for the budget cycle; fire Discord embed once per
  threshold (80%, 100%) per item. HTTP wrapped in try/catch (never break the
  save). Skips transfer legs.
- Hooked after store/bulk-store of expense transactions.
- UI: webhook URL field in settings.

## F4 — CSV export

- Route `GET /transactions/export` (auth, scoped) honoring current filters
  (date range, balance, type, category).
- `StreamedResponse` CSV with UTF-8 BOM (Excel), headers: date, type, amount,
  description, category, balance, transfer_group_id.
- Button in TransactionList toolbar linking to the export with active filters.

## F5–F11 — Backlog (not started)

Detailed specs will be written when each is picked up.
