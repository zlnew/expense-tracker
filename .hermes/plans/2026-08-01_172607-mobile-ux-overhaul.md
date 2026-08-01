# Expense Tracker — Mobile-First UI/UX Overhaul Implementation Plan

> **For Hermes:** Use subagent-driven-development skill to implement this plan task-by-task.

**Goal:** Improve UI/UX of every page in `repo/expense-tracker` with a **mobile-first** focus — better touch targets, bottom-sheet forms, safe-area handling, compact mobile layouts, faster perceived loading — while keeping desktop parity.

**Architecture:** Vue 3 + Inertia v3 SPA (Laravel 13 backend, unchanged). All changes are frontend-only: `resources/js/pages/**`, `resources/js/components/**`, `resources/js/layouts/**`, `resources/js/composables/**`. Reka-UI (shadcn-style) primitives, Tailwind v4, Unovis charts, PWA via `vite-plugin-pwa`.

**Tech Stack:** Vue 3.5, Inertia v3, Tailwind v4, reka-ui 2.x, Unovis, lucide-vue-next, vue-sonner, TypeScript, Pest (backend untouched).

---

## Current-State Analysis (done 2026-08-01)

### Already good (don't regress)
- Mobile bottom nav exists (`AppBottomNav.vue`): Dashboard / Transactions / Budgets / Balances / Profile — fixed, `md:hidden`.
- Mobile card views exist for TransactionList, BudgetList, CategoryList; BudgetDetail + BalanceDetail have mobile section layouts.
- Responsive grids everywhere (`sm:`/`md:`/`lg:` breakpoints).
- CRUD via dialogs (reka-ui) — good pattern to extend, not replace.
- i18n (`en`/`id`), dark mode via `useAppearance`, PWA manifest already configured.
- BalanceList has a nice empty state; budget pages have FABs (`fixed right-4 bottom-20`).

### Concrete mobile problems found (the work)
| # | Area | Problem | File(s) |
|---|------|---------|---------|
| P1 | Bottom nav | Fixed `h-16`, **no iOS safe-area padding** (`env(safe-area-inset-bottom)`); items are small; no quick-add primary action | `AppBottomNav.vue` |
| P2 | Forms | `type="number"` on all amount inputs → wrong keypad on mobile, scroll-zoom on iOS; no `inputmode="decimal"` | `TransactionCreateDialog.vue`, `TransactionUpdateDialog.vue`, `TransactionTransferDialog.vue`, `TransactionBulkCreateDialog.vue`, `BalanceSaveDialog.vue`, `BudgetCreate.vue`, `BudgetEdit.vue` |
| P3 | Dialogs | `DialogContent sm:max-w-[425px]` centers vertically on mobile — should be **bottom sheets** (items-end, rounded-t-2xl, max-h, drag handle) for thumb reach | all `components/dialogs/*` |
| P4 | Touch targets | ~44 icon-only buttons use `size-4` (16px) → below 44px minimum; hit areas too small | TransactionList mobile cards, BalanceList footer, BudgetList cards, CategoryList, dialogs |
| P5 | Dashboard | 4 summary cards stack 1-per-row on mobile (very tall); line chart fixed `h-[300px]`; donut legend scrolls | `Dashboard.vue` |
| P6 | TransactionList | Primary action "Add" hidden in a header dropdown — needs **FAB**; mobile cards cramped; filters/date-range cramped on narrow screens; mobile empty state is plain text | `TransactionList.vue` |
| P7 | BudgetList/CategoryList | Same "Add" buried in header; mobile cards lack strong touch targets | `BudgetList.vue`, `CategoryList.vue` |
| P8 | Header | Mobile header shows logo + breadcrumbs only, no current page title | `AppSidebarHeader.vue` |
| P9 | Pagination | "Showing X to Y of Z" + prev/next wraps awkwardly on tiny screens | `AppPagination.vue` |
| P10 | Welcome/auth | Welcome page hardcodes colors (`#FDFDFC` etc.) instead of theme tokens; loads external Inter from rsms.me (slow on mobile) | `Welcome.vue`, `AuthCardLayout.vue` |
| P11 | Loading | No skeletons/spinners for searches or page loads (debounced `router.get` feels dead) | all list pages |
| P12 | PWA | No install prompt; manifest lacks screenshots; no `theme-color` meta | `vite.config.ts`, `app.blade.php` |

---

## Task Plan

### Phase 1 — Shell & Global Mobile Foundation

#### Task 1: Safe-area + touch-targets in bottom nav
**Objective:** Bottom nav respects iPhone home indicator and has ≥44px touch targets; add a center quick-add button.
**Files:** Modify `resources/js/components/AppBottomNav.vue`
**Steps:**
1. Add `pb-[env(safe-area-inset-bottom)]` to the nav and `h-16` → `h-16 + safe` (e.g. `h-[calc(4rem+env(safe-area-inset-bottom))]`).
2. Bump item tap area: wrap each Link in a `min-h-11 min-w-11` flex container; increase icon `size-5` → `size-6`.
3. Add a center **quick-add FAB** (Plus icon) that emits a global event `open:transaction-create` — TransactionList listens (Task 6). Keep 5 slots max; replace Profile avatar slot position if needed (profile moves to a settings gear on the left or stays as 5th).
4. Verify: `npm run type-check`, view at 390px in devtools → no bottom cut-off, items tappable.

#### Task 2: Mobile header shows page title
**Objective:** On mobile, header displays the current page title (from breadcrumbs last item) instead of just the logo.
**Files:** Modify `resources/js/components/AppSidebarHeader.vue`
**Steps:**
1. `md:hidden` block: if `breadcrumbs.length`, render last breadcrumb title in `text-sm font-semibold` (truncate); else show app name.
2. Keep logo only when no breadcrumbs.
3. Verify: type-check; navigate on 390px viewport → title updates per page.

#### Task 3: Global touch-target min size
**Objective:** Icon-only ghost buttons reach ≥40px (visual) with ≥44px hit area on mobile.
**Files:** Modify — add a small shared class or update inline classes in: `TransactionList.vue` (mobile + desktop action cols), `BalanceList.vue` footer, `BudgetList.vue` cards, `CategoryList.vue` cards, `BudgetDetail.vue`, `BalanceDetail.vue`.
**Steps:**
1. In each icon Button, change `size="icon"` to `size="icon"` + class `h-10 w-10` on mobile only (`h-9 w-9 md:h-8 md:w-8`) OR simpler: `size="sm"` → use `min-h-10 min-w-10`.
2. Verify: no `size-4`-only icon buttons remain in touch rows (grep), type-check.

#### Task 4: Dialogs become bottom sheets on mobile
**Objective:** All CRUD dialogs render as bottom sheets below `md`, centered dialogs at/above `md`.
**Files:** Create `resources/js/components/ui/dialog-sheet.vue` (or extend `DialogContent` usage); modify all 11 dialog components in `components/dialogs/*`.
**Steps:**
1. Add a `SheetDialogContent` wrapper around reka-ui `DialogContent` that applies on mobile: `fixed inset-x-0 bottom-0 top-auto max-h-[90dvh] w-full rounded-t-2xl` + drag-handle bar; on `md+`: keep `sm:max-w-[425px]` centered.
2. Swap `DialogContent` → `SheetDialogContent` in: BalanceCreate/Save/Update/Delete/SetPrimary, BudgetDelete, CategoryCreate/Update/Delete, TransactionCreate/Update/Delete/BulkCreate/Transfer.
3. Add `overscroll-contain` + `overflow-y-auto` so long forms scroll inside the sheet; ensure footer buttons sticky at sheet bottom.
4. Verify: open each dialog at 390px → bottom sheet with handle; at 1280px → centered modal; type-check + build.

#### Task 5: Amount inputs use decimal keypad
**Objective:** All amount inputs open the numeric decimal keypad on mobile and never zoom-scroll.
**Files:** Modify the 7 files from P2.
**Steps:**
1. Replace `type="number"` with `type="text" inputmode="decimal"` + `pattern="[0-9]*[.,]?[0-9]*"` (or keep `type="number"` and add `inputmode="decimal"` — pick per component: if server expects number, safest is `type="text"` + parse, OR `type="number" inputmode="decimal"`).
   **Decision:** use `type="number" inputmode="decimal"` (smallest diff, keeps native validation; stops iOS zoom).
2. Verify: type-check; on mobile the decimal keypad appears, no zoom on focus.

### Phase 2 — Dashboard

#### Task 6: Dashboard summary cards compact on mobile
**Objective:** 4 summary cards fit above the fold on phones — 2×2 grid with tighter padding, or horizontal scroll strip.
**Files:** Modify `resources/js/pages/Dashboard.vue`
**Steps:**
1. Change summary grid: `grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4`; on mobile reduce card padding (`p-4`), title `text-[10px]`, amount `text-xl`.
2. Add `min-w-0` + `truncate` to amounts to avoid overflow with large Rp values.
3. Verify: 390px → 2×2 cards, no overflow; 1440px → 4 across (unchanged).

#### Task 7: Charts responsive height + donut legend
**Objective:** Line chart height adapts to viewport; donut + legend fit mobile without scroll for common data.
**Files:** Modify `resources/js/pages/Dashboard.vue`
**Steps:**
1. Line chart container: `h-[300px]` → `h-[220px] sm:h-[300px]`; add `min-w-0`.
2. Donut: reduce donut size on mobile (`size` prop responsive if supported; else container `max-w-[180px] mx-auto`), legend `max-h` stays but widen tap rows (`py-1.5`).
3. Verify: 390px → charts render, legend scrolls only when >5 categories.

### Phase 3 — List Pages

#### Task 8: TransactionList mobile UX
**Objective:** Primary action becomes a FAB; mobile cards have clear hierarchy + bigger hit targets; filters open as a mobile sheet.
**Files:** Modify `resources/js/pages/TransactionList.vue`, `AppBottomNav.vue` (FAB event already in Task 1)
**Steps:**
1. Add mobile FAB: `fixed right-4 bottom-24 z-50 md:hidden` round primary Button (Plus) → opens `TransactionCreateDialog`; on `md+` keep header dropdown.
2. Mobile cards: increase padding (`p-4`), amount `text-lg`, edit/delete buttons `h-10 w-10`, add category icon avatar (income/expense tinted), date shown as small header chip.
3. Filters: mobile filter button opens a **sheet** (reuse Task 4 sheet) with the 4 filter controls stacked + Apply; hide the inline `grid grid-cols-1` toggle (replace with sheet trigger).
4. Empty state: match BalanceList style (icon circle + title + description + CTA).
5. Verify: 390px → FAB visible above bottom nav, opens create sheet; filters via sheet; desktop table unchanged.

#### Task 9: BudgetList + CategoryList mobile polish
**Objective:** Consistent mobile cards, FAB or header CTA reachable, bigger touch targets.
**Files:** Modify `resources/js/pages/BudgetList.vue`, `resources/js/pages/CategoryList.vue`
**Steps:**
1. BudgetList: keep header CTA but add mobile FAB (`bottom-24`) linking to `BudgetCreate`; card action buttons `h-10 w-10`; active badge readable.
2. CategoryList: mobile cards already exist — bump padding + action sizes; empty state consistent.
3. Verify: type-check; 390px walkthrough.

#### Task 10: Pagination compact on mobile
**Objective:** Pagination doesn't wrap/clutter on narrow screens.
**Files:** Modify `resources/js/components/AppPagination.vue`
**Steps:**
1. On mobile: hide "Showing X to Y of Z" (or shorten to `X–Y of Z`), keep prev/next icon buttons with `h-10 w-10`.
2. Desktop unchanged.
3. Verify: 320px → single row prev/next.

### Phase 4 — Auth & Welcome

#### Task 11: Welcome page full redesign (replace Laravel boilerplate)
**Objective:** Replace the stock Laravel starter page with an expense-tracker branded, mobile-first landing page — hero with product value prop, feature highlights, and clear Login/Register CTAs. No Laravel branding, no hardcoded colors, no external font fetch.
**Files:** Rewrite `resources/js/pages/Welcome.vue`; modify `resources/css/app.css` (font stack), `resources/views/app.blade.php` (font preload removal), `resources/js/components/AppLogo.vue` (brand usage)
**Steps:**
1. Design a new Welcome layout (mobile-first, 360px-first):
   - Top nav: brand logo left; Login / Register buttons right (ghost + primary).
   - Hero: headline ("Track every rupiah") + subcopy + primary CTA (Get started → register) + secondary (Log in); fit above the fold on mobile with no scroll.
   - Feature section: 3–4 compact cards/icons (multi-balance, budgets, reports/charts, transfers) using lucide icons; stack on mobile, 2-col md+, 4-col lg.
   - Footer strip: minimal (app name + year).
2. Replace ALL hardcoded hex colors (`#FDFDFC`, `#1b1b18`, `#161615`, `#706f6c`, `#f53003`, …) with theme tokens (`bg-background`, `text-foreground`, `text-muted-foreground`, `border-border`, `text-primary`, …) so light/dark both work.
3. Delete the Laravel "Let's get started" content, the docs/laracasts link list, the Deploy-now button, and the giant Laravel logo SVG + the rsms.me Inter `<link>`.
4. Ensure the app's logo mark (`AppLogo`/`AppLogoIcon`) is used, not the Laravel wordmark.
5. Verify: build; Welcome renders in light+dark with theme tokens; no external font request in devtools network; 360px hero fits without scroll.

#### Task 12: Auth pages mobile polish
**Objective:** Auth cards comfortable on small screens (no horizontal scroll, generous touch targets).
**Files:** Modify `resources/js/layouts/auth/AuthCardLayout.vue`, auth pages as needed
**Steps:**
1. `AuthCardLayout`: reduce card padding on mobile (`px-6 py-6 sm:px-10 sm:py-8`), inputs `h-11`.
2. Verify: 360px login/register/2FA screens fit without scroll-jank.

### Phase 5 — Sensitive Number Masking

#### Task 13: Mask sensitive numbers (balance, amounts)
**Objective:** Users can hide money values (balances, transaction amounts, budget numbers) behind a mask to prevent shoulder-surfing; toggle is global and persisted.
**Files:** Modify `resources/js/composables/useNumber.ts`; create `resources/js/composables/useMasking.ts`; modify `AppSidebarHeader.vue` + `AppBottomNav.vue` (toggle control); modify all pages/components that render money.
**Steps:**
1. Create `useMasking.ts`: reactive `masked` boolean, initialized from `localStorage` (key `expense-tracker.masked`, default `false`), `toggleMask()`, and a computed `mask(value)` helper that returns `'••••••'` (or `'Rp •••'` — pick a fixed-width mask like `'Rp ••••••'`) when masked, else the formatted value.
2. Extend `useNumber.ts` with a `formatAmount(value)` that applies `formatNumber` then the mask when masking is on (keeps one call site for money; keep `formatNumber` unmasked for non-sensitive stats).
3. Add a mask toggle control in the header (`AppSidebarHeader.vue`) — an eye/eye-off icon button (`h-9 w-9`, tooltip "Show/hide balances") visible at all breakpoints; and mirror it in `AppBottomNav.vue` (or replace the Profile avatar slot with avatar + a small eye toggle) so mobile users can toggle without opening menus.
4. Apply masked rendering to every money display: Dashboard summary cards + recent transactions + donut legend amounts, TransactionList mobile cards + desktop table amount cells, BalanceList + BalanceDetail, BudgetList + BudgetDetail (planned/actual/remaining), BudgetCreate/Edit previews if any.
5. Persist: `watch(masked)` → `localStorage.setItem`. Toggle survives navigation (SPA) and reload.
6. Verify: type-check; toggle on → all money values show `Rp ••••••` across pages; toggle off → values return; reload keeps the setting; dark mode unaffected.

### Phase 6 — Loading, PWA, Verification

#### Task 14: Loading skeletons for list/search
**Objective:** Debounced search + page loads show feedback instead of dead UI.
**Files:** Modify list pages (`TransactionList.vue`, `BalanceList.vue`, `BudgetList.vue`, `CategoryList.vue`) + optionally a shared `ListSkeleton.vue`
**Steps:**
1. Create `resources/js/components/ListSkeleton.vue` (3–5 shimmer cards for mobile, table rows for md+).
2. Wire a `loading` ref set true during `router.get` (before, false on finish) in each list page; render skeleton in place of the data container while loading.
3. Verify: throttled network → skeletons appear on search.

#### Task 15: PWA install prompt + manifest polish
**Objective:** Users can install the app; install hint appears once.
**Files:** Modify `resources/js/app.ts` (or new `composables/useInstallPrompt.ts`), `vite.config.ts` (manifest screenshots), `resources/views/app.blade.php` (theme-color meta)
**Steps:**
1. Add `useInstallPrompt` composable capturing `beforeinstallprompt`; show a small dismissible banner/toast on Dashboard (once per session).
2. Add `screenshots` entries to manifest (1920×1080 capture) + `id`.
3. Add `<meta name="theme-color" content="...">` in blade.
4. Verify: build; installability in Chrome (audit "PWA installable").

#### Task 16: Full verification pass
**Objective:** No regressions; every page QA'd at 360/390px, 768px, 1440px.
**Steps:**
1. `npm run lint-check`, `npm run type-check`, `npm run build`.
2. `php artisan test` (Pest) — backend untouched, expect green.
3. Manual QA checklist (mobile first): Dashboard, TransactionList (+create/edit/delete/transfer/bulk), BalanceList/Detail, BudgetList/Create/Edit/Detail, CategoryList, Settings (Profile/Security/Appearance), Auth pages, Welcome, dark mode, PWA install.
4. Fix any overflow/contrast/touch issues found; commit.

---

## Files Likely to Change (summary)
- **New:** `components/ui/dialog-sheet.vue`, `components/ListSkeleton.vue`, `composables/useMasking.ts`, `composables/useInstallPrompt.ts`
- **Modify (shell):** `AppBottomNav.vue`, `AppSidebarHeader.vue`, `AppPagination.vue`
- **Modify (pages):** `Dashboard.vue`, `TransactionList.vue`, `BalanceList.vue`, `BudgetList.vue`, `CategoryList.vue`, `BudgetDetail.vue`, `BalanceDetail.vue`, `Welcome.vue` (full rewrite), auth pages
- **Modify (dialogs):** all 11 under `components/dialogs/*` (sheet wrapper + inputmode)
- **Modify (money rendering):** `composables/useNumber.ts` (formatAmount + masking), every page/component that renders Rp (Dashboard, lists, details) — masking pass
- **Modify (config):** `vite.config.ts`, `app.ts`, `app.blade.php`, `app.css`, `package.json` (Inter local font if chosen)

## Tests / Validation
- `npm run type-check` (vue-tsc), `npm run lint-check`, `npm run build` — must pass after every task.
- `php artisan test` — must stay green (no backend changes).
- Manual responsive QA at 360px / 390px / 768px / 1440px + dark mode (checklist in Task 15).

## Risks / Tradeoffs / Open Questions
- **Bottom-sheet dialogs** change the interaction model on mobile — verify no reka-ui regression; keep desktop modal identical.
- **FAB + bottom-nav overlap** — FAB must sit above the safe-area nav (`bottom-24` / `bottom-[calc(4rem+env(safe-area-inset-bottom)+0.75rem)]`).
- **Welcome rewrite** is visual-only; do not alter route/backend.
- **Local Inter font** adds a dependency; if budget is a concern, use system font stack instead (no new dep).
- **Open question:** should the quick-add FAB open transaction-create only, or offer a menu (transaction/transfer/bulk)? Default: transaction-create only (most common); menu is a stretch.
- **Scope guardrail:** frontend-only. No route/controller/model changes unless a task explicitly says so.

---

## Execution Handoff
Plan complete and saved. Ready to execute using subagent-driven-development — I'll dispatch a fresh subagent per task with two-stage review (spec compliance then code quality). Shall I proceed?
