<?php

namespace App\Actions;

use App\Enums\CategoryType;
use App\Models\BudgetItem;
use App\Models\Transaction;
use App\Models\User;
use App\Support\BudgetCycle;
use Illuminate\Support\Facades\Http;

/**
 * Check a freshly-saved expense against its budget items and push a Discord
 * webhook notification when an item crosses 80% or 100% of its planned amount.
 *
 * Spend is scoped to the CURRENT budget cycle (cutoff-day aware) — last
 * cycle's expenses must not inflate this cycle's percentage. Alerts re-arm
 * each cycle: alert_cycle_key records the cycle the flags were set in.
 *
 * Deliberately fire-and-forget: webhook failures never affect the save.
 */
class CheckBudgetAlerts extends Action
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

        if (! $this->user->discord_webhook_url) {
            return;
        }

        $budgetItem = $this->transaction->budgetItem;

        if (! $budgetItem || $budgetItem->planned_amount <= 0) {
            return;
        }

        [$start, $end] = BudgetCycle::currentCycleRange($budgetItem->budget);

        $spent = Transaction::query()
            ->where('user_id', $this->user->id)
            ->where('budget_item_id', $budgetItem->id)
            ->where('type', CategoryType::EXPENSE)
            ->whereBetween('date', [$start, $end])
            ->sum('amount');

        $percentage = round(($spent / $budgetItem->planned_amount) * 100);

        $cycleKey = $start->toDateString();

        // Flags are per-cycle: a new cycle re-arms both alerts.
        $alertsFiredThisCycle = $budgetItem->alert_cycle_key === $cycleKey;

        $messages = [];

        if ($percentage >= 100 && (! $alertsFiredThisCycle || ! $budgetItem->alert_100_sent)) {
            $messages[] = $this->message($budgetItem, $spent, $percentage, '🔴');
            // Crossing 100% implies the 80% threshold was crossed too —
            // mark both so the 80% alert never fires afterwards.
            $budgetItem->update([
                'alert_80_sent' => true,
                'alert_100_sent' => true,
                'alert_cycle_key' => $cycleKey,
            ]);
        } elseif ($percentage >= 80 && (! $alertsFiredThisCycle || ! $budgetItem->alert_80_sent)) {
            $messages[] = $this->message($budgetItem, $spent, $percentage, '🟠');
            $budgetItem->update([
                'alert_80_sent' => true,
                'alert_cycle_key' => $cycleKey,
            ]);
        }

        if ($messages === []) {
            return;
        }

        try {
            Http::post($this->user->discord_webhook_url, [
                'content' => implode("\n", $messages),
            ]);
        } catch (\Throwable $e) {
            // Never break the transaction save because of a webhook failure.
            report($e);
        }
    }

    private function message(BudgetItem $budgetItem, int $spent, int $percentage, string $emoji): string
    {
        $category = $budgetItem->category?->name ?? 'Unknown';
        $planned = number_format($budgetItem->planned_amount, 0, ',', '.');

        return sprintf(
            '%s Budget alert: **%s** is at %d%% of its Rp %s budget (Rp %s spent).',
            $emoji,
            $category,
            $percentage,
            $planned,
            number_format($spent, 0, ',', '.'),
        );
    }
}
