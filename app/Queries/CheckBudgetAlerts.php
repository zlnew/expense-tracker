<?php

namespace App\Queries;

use App\Enums\CategoryType;
use App\Models\BudgetItem;
use App\Models\Transaction;
use App\Models\User;
use App\Support\BudgetActuals;
use App\Support\BudgetCycle;
use Illuminate\Support\Facades\Http;

/**
 * Check a freshly-saved expense against its budget items and push a Discord
 * webhook notification when an item crosses 80% or 100% of its planned amount.
 *
 * Envelope-basis (2026-08-16 spec): the percentage uses BudgetActuals — the
 * same envelope-aware number the budget page shows. Fund set-asides count as
 * used (the reservation IS the budget movement); fund payouts are excluded
 * (budget-exempt). Fund set-asides fire through CheckFundBudgetAlerts.
 *
 * Spend is scoped to the CURRENT budget cycle (cutoff-day aware) — last
 * cycle's expenses must not inflate this cycle's percentage. Alerts re-arm
 * each cycle: alert_cycle_key records the cycle the flags were set in.
 *
 * Deliberately fire-and-forget: webhook failures never affect the save.
 */
class CheckBudgetAlerts extends Query
{
    public function __construct(
        private readonly User $user,
        private readonly Transaction $transaction,
    ) {}

    public function handle(): void
    {
        // Only expenses against a budget item can trigger an alert.
        if ($this->transaction->type !== CategoryType::EXPENSE || ! $this->transaction->budget_id) {
            return;
        }

        $budgetItem = $this->transaction->budgetItem;

        if (! $budgetItem) {
            return;
        }

        self::evaluate($this->user, $budgetItem);
    }

    /**
     * Evaluate a budget item against envelope-aware current-cycle actuals
     * and fire the webhook when a threshold is crossed. Shared entry path
     * for regular expenses (handle) and fund set-asides (CheckFundBudgetAlerts).
     */
    public static function evaluate(User $user, BudgetItem $budgetItem): void
    {
        if (! $user->discord_webhook_url) {
            return;
        }

        if ($budgetItem->planned_amount <= 0) {
            return;
        }

        [$start, $end] = BudgetCycle::currentCycleRange($budgetItem->budget);

        $actuals = BudgetActuals::perItem($user, $budgetItem->budget, $start, $end);

        $used = $actuals[$budgetItem->id] ?? 0;

        $percentage = round(($used / $budgetItem->planned_amount) * 100);

        $cycleKey = $start->toDateString();

        // Flags are per-cycle: a new cycle re-arms both alerts.
        $alertsFiredThisCycle = $budgetItem->alert_cycle_key === $cycleKey;

        $messages = [];

        if ($percentage >= 100 && (! $alertsFiredThisCycle || ! $budgetItem->alert_100_sent)) {
            $messages[] = self::message($budgetItem, $used, $percentage, '🔴');
            // Crossing 100% implies the 80% threshold was crossed too —
            // mark both so the 80% alert never fires afterwards.
            $budgetItem->update([
                'alert_80_sent' => true,
                'alert_100_sent' => true,
                'alert_cycle_key' => $cycleKey,
            ]);
        } elseif ($percentage >= 80 && (! $alertsFiredThisCycle || ! $budgetItem->alert_80_sent)) {
            $messages[] = self::message($budgetItem, $used, $percentage, '🟠');
            $budgetItem->update([
                'alert_80_sent' => true,
                'alert_cycle_key' => $cycleKey,
            ]);
        }

        if ($messages === []) {
            return;
        }

        try {
            Http::post($user->discord_webhook_url, [
                'content' => implode("\n", $messages),
            ]);
        } catch (\Throwable $e) {
            // Never break the transaction save because of a webhook failure.
            report($e);
        }
    }

    private static function message(BudgetItem $budgetItem, int $used, int $percentage, string $emoji): string
    {
        $category = $budgetItem->category?->name ?? 'Unknown';
        $planned = number_format($budgetItem->planned_amount, 0, ',', '.');

        return sprintf(
            '%s Budget alert: **%s** is at %d%% of its Rp %s budget (Rp %s used).',
            $emoji,
            $category,
            $percentage,
            $planned,
            number_format($used, 0, ',', '.'),
        );
    }
}
