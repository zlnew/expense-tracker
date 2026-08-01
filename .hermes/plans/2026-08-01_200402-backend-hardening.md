# Expense Tracker — Backend Architecture Hardening Plan

**Goal:** Fix the critical multi-tenant isolation gap, correct real bugs, consolidate duplicated domain logic, localize strings, and add Pest coverage — per Maul's architecture review (2026-08-01). Point 7 (categories) decided: **per-user categories**.

**Architecture:** Laravel 13 + PHP 8.4, Inertia v3, `spatie/laravel-data` DTOs, custom `app/Queries/*`, `app/Actions/*` service layer, Pest for tests. Money = integer rupiah (`bigInteger`).

---

## Current-State Summary (done 2026-08-01)

### Critical
- **C1 — No user scoping on reads.** `TransactionController::index`, `BalanceController::index`, `BudgetController::index`, and `GetTransactionApiController` build queries from `$request->all()` and **never force `user_id`**. Any authed user sees every user's data. Verified live: user 2 can see user 1's transactions.
- **C2 — No ownership checks on mutations.** Route-model-bound `show/update/destroy` + `setActive`/`setPrimary` never verify the resource belongs to `Auth::id()`. Any authed user can edit/delete another user's record by ID.
- **C3 — Transfer insufficient-balance check is wrong.** `TransferBetweenAccounts.php:35` checks `final_amount < 0` instead of `< amount`; allows overdrawing.
- **C4 — Categories are global AND mutable by any user** (`Category::all()`, no `user_id`). Decided: **per-user** (migration + seed-on-register + scoping + ownership).

### Bugs / debt
- **B1 — Transfer creates two orphaned transactions** (no transfer grouping; can't cancel as a pair).
- **B2 — `GetRecentTransactions` uses calendar month** while summary/budget-progress use the **cutoff cycle** — widgets disagree near cutoff day.
- **B3 — `SaveBudget` never deletes removed items** → stale `budget_item` rows.
- **B4 — Cycle logic duplicated 4–5×**: `getCutoffDateForMonth`/`getCurrentCycleRange` copy-pasted in `GetSummaryCardsData`, `GetBudgetProgress`, `GetExpenseBreakdown`; raw SQL variants in `TransactionQuery` + `GetMonthlySpendingTrend`.
- **B5 — Hardcoded English error strings** in actions (incl. "Cannon delete primary balance" typo) — not in the translations pipeline.
- **B6 — Zero tests** for domain logic (cycle math, balance sync, transfers, authz).

---

## Tasks

### Phase 1 — Security (do first, alone)

**T1. Force user scoping on all read paths** *(C1)*
- Files: `app/Http/Controllers/TransactionController.php` (index), `BalanceController.php` (index), `BudgetController.php` (index), `CategoryController.php` (index), `app/Http/Controllers/Api/GetTransactionApiController.php`, `app/Queries/*`.
- Do: add a `user`-scoping helper on `BaseQuery` (e.g. `scopeToUser(int $userId)` or a `user_id` filter the controller always applies server-side — never trust the client to send it). Ensure every index/API path ends with `->where('user_id', Auth::id())`.
- Category index scoping deferred to T7 (per-user categories).
- Verify: Pest authz tests (T8) + manual two-user check.

**T2. Ownership checks on route-model-bound mutations** *(C2)*
- Files: `TransactionController` (update/destroy), `BalanceController` (show/update/destroy/setPrimary), `BudgetController` (show/update/destroy/setActive), `CategoryController` (update/destroy, tied to T7).
- Do: simplest robust option = a tiny `EnsureOwnership` middleware or per-action `abort_unless($model->user_id === Auth::id(), 403)`. For a personal app, explicit checks in controllers are fine — no need for full Policy classes unless we want them.
- Verify: Pest authz tests (T8).

**T3. Fix transfer bugs** *(C3 + B1)*
- Files: `app/Actions/TransferBetweenAccounts.php`, `app/Actions/DeleteTransaction.php`, migration (new `transactions.transfer_group_id` nullable uuid/string), `app/DTO/TransactionData.php`, `app/Http/Requests/TransactionSaveRequest.php` (accept optional `transfer_group_id`).
- Do:
  1. Check `sourceAccount->final_amount < amount` → ValidationException.
  2. Generate a shared `transfer_group_id` for the two created transactions (source expense + destination income).
  3. `DeleteTransaction`: if `transfer_group_id` set, delete the pair atomically (and resync both balances).
- Verify: Pest tests for insufficient funds + pair deletion (T8).

### Phase 2 — Domain consolidation

**T4. Extract shared `BudgetCycle` domain service** *(B2 + B4)*
- Files: new `app/Support/BudgetCycle.php` (or `app/Services/BudgetCycle.php`), refactor `GetSummaryCardsData`, `GetBudgetProgress`, `GetExpenseBreakdown`, `GetRecentTransactions`, `TransactionQuery`, `GetMonthlySpendingTrend`.
- Do:
  1. PHP helpers `cutoffDateForMonth(CarbonImmutable $date, int $cutoffDay)` + `currentCycleRange(?Budget $budget)` living in ONE class; delete the 3 copies.
  2. SQL helpers: one `BudgetCycle::cycleDateSql(string $dateColumn)` fragment used by `TransactionQuery::month/year` and `GetMonthlySpendingTrend` (replaces both raw-SQL variants).
  3. `GetRecentTransactions`: use the cycle range instead of calendar month.
- Verify: Pest tests for cycle boundary (T8) + `php artisan test` + dashboard renders.

**T5. `SaveBudget` prunes removed items** *(B3)*
- Files: `app/Actions/SaveBudget.php`.
- Do: after upserting items from the DTO, `delete` budget items belonging to this budget whose id is not in the submitted set. Respect the budget's expense/income type groups.
- Verify: Pest test (T8) + manual edit-remove roundtrip.

### Phase 3 — i18n + categories

**T6. Localize hardcoded action strings** *(B5)*
- Files: `TransferBetweenAccounts.php`, `DeleteBalance.php` (fix "Cannon" typo), `DeleteBudget.php`, `DeleteCategory.php`, `lang/translations.csv`.
- Do: move all inline English error strings to `translations.csv` + run `php artisan app:generate-language-files`; reference via `__()`.
- Verify: grep for leftover hardcoded strings; generator output diff.

**T7. Per-user categories** *(C4)*
- Files: migration (`categories.user_id` nullable FK → backfill → not null), `app/Models/Category.php`, `app/Actions/Fortify/CreateNewUser.php` (seed defaults on register), `database/seeders/CategorySeeder.php` (become per-user template), `CategoryController` (index scoping + ownership), `CategoryQuery`, `app/Http/Middleware/HandleInertiaRequests.php` (share only user's categories), any dialog/picker that lists categories.
- Do:
  1. Migration adds `user_id`; backfill: existing users get a clone of the global default category set; global rows removed (or kept as nullable seed rows — decide during implementation; prefer clean: per-user rows only).
  2. `CreateNewUser` seeds the default set for the new user (from a shared `DefaultCategories` source, not hardcoded twice).
  3. All category reads scoped by user; update/destroy ownership-checked.
- Verify: new-user registration → default categories present; two users can't see/edit each other's categories; Pest test (T8).

### Phase 4 — Tests

**T8. Pest coverage for domain + authz**
- Files: `tests/Feature/*` (new), `tests/Pest.php` exists.
- Do, covering: cycle boundary math (day before/after cutoff, month-end clamp), `SyncBalance` math (create/update/delete/transfer), insufficient-funds rejection, transfer pair deletion, budget-item pruning, per-user scoping (user A cannot read/write user B data — both web + API).
- Verify: `php artisan test` green.

---

## Execution order & batching
1. **T1 + T2 alone** (security, highest priority, no parallel conflicts — controllers only).
2. **T3 alone** (new migration + action changes).
3. **T4 alone** (touches many action/query files — cross-cutting, must not be parallelized with anything).
4. **T5 alone** (small, one action).
5. **T6 alone** (translations CSV — generator rewrites lang files).
6. **T7 alone** (migration + registration hook + scoping — the biggest task).
7. **T8 last** (tests reference all the above).

## Files touched (estimate)
~25 files: 5 controllers, 1 API controller, ~10 actions, 3 queries, 2 models, 1 DTO, 1 request, 3 migrations, seeder, `HandleInertiaRequests`, translations.csv, new tests.

## Verification per batch
`docker exec -w /app expense-tracker sh -c 'php artisan test'` (after T8: `pint` too). Manual: two-user tinker check after T1/T2/T7.
