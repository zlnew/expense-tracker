# Financial UX, Timezone, and Projection Logic Fixes Plan

**Date:** 2026-09-19  
**Target Repo:** `repo/expense-tracker`  
**Status:** Proposal / Pending Maul's Greenlight  
**Plan File:** `.hermes/plans/2026-09-19-financial-ux-and-logic-fixes.md`

---

## 1. WHY: Audit & Root Cause Analysis

We audited the four areas identified in the user request. Here are the exact findings and technical root causes:

### Issue 1: Drift & Reconcile Mechanism is Misleading
* **Current Behavior:**
  - In [`Balance.php`](file:///home/zlnew/www/personal/repo/expense-tracker/app/Models/Balance.php#L84-L102), `drift` is calculated as `final_amount - reconciled_amount`, and `is_drift_flagged` is `true` if `abs(drift) > 500`.
  - When a user reconciles on Day 1 (e.g. Bank = Rp 1.000.000, Ledger = Rp 1.000.000), `drift` is 0 ("Within tolerance").
  - On Day 2, the user spends Rp 50.000. `final_amount` becomes Rp 950.000, but `reconciled_amount` remains Rp 1.000.000.
  - Instantly, `drift` becomes `-50.000`, and `is_drift_flagged` evaluates to `true`.
  - On the UI ([`BalanceList.vue`](file:///home/zlnew/www/personal/repo/expense-tracker/resources/js/pages/BalanceList.vue#L272-L305) and [`BalanceDetail.vue`](file:///home/zlnew/www/personal/repo/expense-tracker/resources/js/pages/BalanceDetail.vue#L240-L268)), every balance card permanently turns red with an `AlertTriangle` warning: `"Drift flagged / Selisih terdeteksi (-50.000)"`.
* **The Flaw:**
  - `reconciled_amount` is a point-in-time snapshot, but it is continuously compared against the *live* balance of today rather than the balance *as of the reconciliation date*. Normal daily spending is flagged as an accounting error/discrepancy.
  - Furthermore, "drift" is conflated across the codebase: [`SyncFinancialIntegrity`](file:///home/zlnew/www/personal/repo/expense-tracker/app/Actions/SyncFinancialIntegrity.php) uses "drift" for internal database bugs (`final_amount != initial_amount + SUM(txns)`), whereas [`Balance.php`](file:///home/zlnew/www/personal/repo/expense-tracker/app/Models/Balance.php) uses "drift" for statement differences.

### Issue 2: Timezone Inconsistencies (Backend vs. Frontend)
* **Backend:**
  - [`config/app.php`](file:///home/zlnew/www/personal/repo/expense-tracker/config/app.php#L68) hardcodes `'timezone' => 'UTC'`. It does not even read `env('APP_TIMEZONE', 'UTC')`.
  - Server-side calls to `now()->toDateString()` or `CarbonImmutable::now()` run in UTC. For a user in Western Indonesia Time (WIB / `Asia/Jakarta`, UTC+7), between 00:00 and 06:59 AM, the server considers it to be *yesterday*. Any transaction logged during these hours is given yesterday's date.
* **Frontend:**
  - In [`TransactionList.vue`](file:///home/zlnew/www/personal/repo/expense-tracker/resources/js/pages/TransactionList.vue#L139-L143) and [`BalanceReconcileDialog.vue`](file:///home/zlnew/www/personal/repo/expense-tracker/resources/js/components/dialogs/BalanceReconcileDialog.vue#L42), code calls `new Date().toISOString().slice(0, 10)` to determine "today".
  - `toISOString()` converts the browser's local time to UTC. In UTC+7 early morning, this produces yesterday's date. As a result, transactions logged on the 20th are grouped under "Kemarin" or a date header, while the 19th is labeled "Hari Ini".
  - In [`useDate.ts`](file:///home/zlnew/www/personal/repo/expense-tracker/resources/js/composables/useDate.ts), passing date-only strings (`"YYYY-MM-DD"`) to `new Date("YYYY-MM-DD")` triggers ECMAScript UTC-midnight parsing (`"YYYY-MM-DDTHH:mm:ss.sssZ"`), causing local timezone shifts.

### Issue 3: Transaction List Shows Static "00:00"
* **Current Behavior:**
  - In [`TransactionList.vue`](file:///home/zlnew/www/personal/repo/expense-tracker/resources/js/pages/TransactionList.vue#L623-L625):
    ```vue
    <p class="mt-0.5 font-mono text-[10px] text-zinc-500">
      {{ formatDate(t.date, 'HH:mm') }}
    </p>
    ```
* **The Flaw:**
  - The `transactions.date` column in the database is a `DATE` field (`2026-08-20`), with no time component.
  - [`TransactionData.php`](file:///home/zlnew/www/personal/repo/expense-tracker/app/DTO/TransactionData.php#L23-L25) formats it as `'Y-m-d'`.
  - Formatting a date-only string with format `'HH:mm'` always yields `'00:00'`.
  - Transactions in `TransactionList.vue` are already grouped by day under headers (`Hari Ini`, `Kemarin`, `20 Agustus 2026`). Displaying `'00:00'` underneath the amount is both broken and redundant.

### Issue 4: Impending Drains 30d, 60d, 90d Horizon Returns Identical Data
* **Current Behavior:**
  - Switching between `30d`, `60d`, and `90d` produces the exact same items and the exact same `total_impending_outflow`.
* **The Flaw:**
  - In [`GetImpendingDrains.php`](file:///home/zlnew/www/personal/repo/expense-tracker/app/Queries/GetImpendingDrains.php#L23-L26):
    > *"Only active recurrings count; each recurring contributes at most ONE pending occurrence (its next_run_date) if that date falls inside the window — we do not fan out hypothetical future cycles."*
  - Sinking funds similarly only query `sinking_funds.next_due <= until` for a single upcoming contribution.
  - Because recurring expenses (e.g. monthly subscriptions, rent) and sinking fund contributions have monthly cycles, their single `next_run_date` and `next_due` almost always fall within the next 30 days.
  - At 30 days: All upcoming monthly items appear once.
  - At 60 days: No additional occurrences are projected. The exact same items appear once.
  - At 90 days: No additional occurrences are projected. The exact same items appear once.
  - Unless an item happens to have its *first* occurrence between day 31 and day 90, the 30d, 60d, and 90d horizons return identical results. This defeats the purpose of an impending drains projection.

---

## 2. WHAT: Scope, Non-Goals, and Boundaries

### In Scope
1. **Reconcile & Drift Redesign:**
   - Redefine reconciliation as a point-in-time statement audit rather than a persistent comparison against live balance.
   - Remove misleading red "Drift Flagged" banners on regular balance cards when subsequent transactions occur.
   - When reconciling, compute drift against the ledger balance *as of the reconciliation date*.
   - Provide an optional "Adjust Balance" option if there is an unexplained discrepancy at reconciliation time.
2. **Timezone Alignment:**
   - Update [`config/app.php`](file:///home/zlnew/www/personal/repo/expense-tracker/config/app.php) to support `env('APP_TIMEZONE', 'Asia/Jakarta')` (defaulting to user/server timezone).
   - Update frontend date helpers to compute "today" and "yesterday" using browser local date components (`getFullYear()`, `getMonth()`, `getDate()`) instead of UTC `toISOString().slice(0, 10)`.
   - Prevent date shifts when formatting `"YYYY-MM-DD"` strings in [`useDate.ts`](file:///home/zlnew/www/personal/repo/expense-tracker/resources/js/composables/useDate.ts).
3. **Transaction List Clean-up:**
   - Remove the broken `{{ formatDate(t.date, 'HH:mm') }}` line from [`TransactionList.vue`](file:///home/zlnew/www/personal/repo/expense-tracker/resources/js/pages/TransactionList.vue).
   - Display relevant metadata instead (e.g. transfer badge or clean layout without `00:00`).
4. **Impending Drains Multi-Cycle Projection:**
   - Update [`GetImpendingDrains.php`](file:///home/zlnew/www/personal/repo/expense-tracker/app/Queries/GetImpendingDrains.php) to fan out occurrences across the horizon window:
     - For recurring transactions: project each occurrence based on `frequency` (`daily`, `weekly`, `monthly`, `quarterly`, `yearly`) within `[today, today + windowDays]`.
     - For sinking funds: project future contributions based on `cadence` / `due_interval_months` within `[today, today + windowDays]`.
   - 30d, 60d, and 90d will accurately reflect 1, 2, or 3 monthly cycles.

### Out of Scope / Non-Goals
- Migrating `transactions.date` from `DATE` to `TIMESTAMP`/`DATETIME` (unnecessary complexity; all budget cutoff and cycle logic is date-based).
- Implementing bank API integrations / automated Open Banking scraping.

---

## 3. HOW: Implementation Steps & Checkable Criteria

### Step 1: Timezone Alignment (Backend & Frontend)
- **Backend:**
  - Modify [`config/app.php`](file:///home/zlnew/www/personal/repo/expense-tracker/config/app.php#L68) to:
    ```php
    'timezone' => env('APP_TIMEZONE', 'Asia/Jakarta'),
    ```
  - Ensure `.env` and `.env.example` specify `APP_TIMEZONE=Asia/Jakarta`.
- **Frontend:**
  - Create a robust `getLocalDateString(d: Date = new Date()): string` helper in [`useDate.ts`](file:///home/zlnew/www/personal/repo/expense-tracker/resources/js/composables/useDate.ts) that formats `YYYY-MM-DD` using local calendar values (`year`, `month.padStart(2, '0')`, `day.padStart(2, '0')`).
  - Replace `new Date().toISOString().slice(0, 10)` in [`TransactionList.vue`](file:///home/zlnew/www/personal/repo/expense-tracker/resources/js/pages/TransactionList.vue) and [`BalanceReconcileDialog.vue`](file:///home/zlnew/www/personal/repo/expense-tracker/resources/js/components/dialogs/BalanceReconcileDialog.vue) with `getLocalDateString()`.
- **Checkable Criteria:**
  - At 01:00 AM WIB, creating a transaction or viewing "Hari Ini" groups transactions under the correct current date (no 7-hour lag).

### Step 2: Fix Transaction List Time Display
- **Frontend:**
  - In [`TransactionList.vue`](file:///home/zlnew/www/personal/repo/expense-tracker/resources/js/pages/TransactionList.vue#L623-L626), remove `{{ formatDate(t.date, 'HH:mm') }}`.
  - Clean up the amount column so it cleanly displays the formatted amount without an empty or artificial `00:00` timestamp.
- **Checkable Criteria:**
  - Transaction list shows no `'00:00'` labels under amounts.

### Step 3: Impending Drains Horizon Projection (Multi-Cycle Fan-out)
- **Backend:**
  - In [`GetImpendingDrains.php`](file:///home/zlnew/www/personal/repo/expense-tracker/app/Queries/GetImpendingDrains.php):
    - When iterating recurring expenses, loop and project occurrences from `next_run_date` until `until` date based on `frequency` (e.g. `addWeek()`, `addMonthNoOverflow()`, `addYear()`).
    - When iterating sinking funds, project future contributions from `next_due` (or current contribution interval) until `until` date.
    - Aggregate all projected occurrences in `items` and calculate `impendingByBalance`.
- **Checkable Criteria:**
  - A monthly recurring expense of Rp 100.000 shows:
    - In 30d: 1 occurrence (Total: Rp 100.000).
    - In 60d: 2 occurrences (Total: Rp 200.000).
    - In 90d: 3 occurrences (Total: Rp 300.000).
  - Switching between 30d, 60d, and 90d updates the UI with distinct totals and projected free balance.

### Step 4: Reconcile & Drift Mechanism Redesign
- **Backend:**
  - In [`Balance.php`](file:///home/zlnew/www/personal/repo/expense-tracker/app/Models/Balance.php):
    - Rework `getDriftAttribute()`:
      - Option A (Point-in-time): Compute ledger balance *as of* `reconciled_at` (`initial_amount + SUM(incomes up to reconciled_at) - SUM(expenses up to reconciled_at)`). Compare this historical ledger balance against `reconciled_amount`. Transactions occurring *after* `reconciled_at` do not alter the reconciliation status of that past date.
      - Option B (Audit Snapshot): Reconcile is an explicit confirmation of bank balance. If drift exists at reconciliation time, the user can choose to accept it or create an auto-adjustment transaction.
- **Frontend:**
  - On [`BalanceList.vue`](file:///home/zlnew/www/personal/repo/expense-tracker/resources/js/pages/BalanceList.vue), remove the constant red "Drift Flagged" badge from normal balance cards. Replace with a subtle "Reconciled on [Date]" indicator.
  - In [`BalanceReconcileDialog.vue`](file:///home/zlnew/www/personal/repo/expense-tracker/resources/js/components/dialogs/BalanceReconcileDialog.vue), show the ledger balance as of the selected date, show the drift clearly *in the modal* before confirming, and explain what the discrepancy is.
- **Checkable Criteria:**
  - Reconciling an account and subsequently adding new transactions does not turn the account red or claim a drift error.

---

## 4. DECISIONS & RISKS NEEDING MAUL'S SIGN-OFF

| # | Decision Topic | Options | Recommendation |
|---|---|---|---|
| 1 | **Reconcile & Drift UI Behavior** | **Option A:** Point-in-time calculation (ledger balance as of `reconciled_at` vs `reconciled_amount`). Future transactions do not break reconciliation.<br>**Option B:** Remove persistent drift banners from balance cards completely; keep reconciliation purely inside the modal with an optional "Adjust balance" transaction. | **Option A + B:** Use point-in-time math AND remove the noisy red strip from balance cards. |
| 2 | **Impending Drains Fan-out** | **Option A:** Fan out all recurring and sinking fund cycles up to the horizon window (30d = 1x, 60d = 2x, 90d = 3x for monthly).<br>**Option B:** Keep single next occurrence, but change the UI labels from "30d / 60d / 90d" to something else. | **Option A:** Fan out the cycles so 30d/60d/90d represents true future cash flow drain. |
| 3 | **Timezone Configuration** | Set `APP_TIMEZONE=Asia/Jakarta` as default in `config/app.php` and `.env`. | **Approve:** User is in WIB (UTC+7). |

---

## Verification & Greenlight Gate
This plan is documentation-only. No application code has been modified. Awaiting Maul's approval before proceeding with implementation.
