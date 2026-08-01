<?php

namespace App\Actions;

use App\DTO\TransactionData;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProcessRecurringTransactions extends Action
{
    /**
     * Create transactions for every due recurring schedule.
     *
     * @param  int|null  $userId  Restrict to one user (dashboard lazy fallback);
     *                            null processes everyone (scheduler).
     */
    public function handle(?int $userId = null): int
    {
        $processed = 0;

        $query = RecurringTransaction::query()
            ->where('is_active', true)
            ->whereDate('next_run_date', '<=', now()->toDateString())
            ->with(['user', 'balance', 'category']);

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        $dueRecurrings = $query->get();

        // SaveTransaction scopes the row to Auth::id(), so impersonate the
        // recurring's owner while creating it (scheduler has no session).
        $previousUser = Auth::user();

        foreach ($dueRecurrings as $recurring) {
            Auth::setUser($recurring->user);

            if ($this->runOnce($recurring)) {
                $processed++;
            }
        }

        if ($previousUser) {
            Auth::setUser($previousUser);
        } else {
            Auth::logout();
        }

        return $processed;
    }

    /**
     * Create the transaction for one due recurring and advance its schedule.
     */
    private function runOnce(RecurringTransaction $recurring): bool
    {
        return DB::transaction(function () use ($recurring) {
            $runDate = $recurring->next_run_date;

            SaveTransaction::run(new Transaction, TransactionData::from([
                'balance_id' => $recurring->balance_id,
                'budget_id' => null,
                'budget_item_id' => null,
                'category_id' => $recurring->category_id,
                'type' => $recurring->type->value,
                'date' => $runDate,
                'amount' => $recurring->amount,
                'description' => $recurring->description,
            ]));

            $next = $this->advance($recurring->frequency, $runDate);

            if ($recurring->end_date && $next->greaterThan($recurring->end_date)) {
                $recurring->update(['is_active' => false]);

                return true;
            }

            $recurring->update(['next_run_date' => $next]);

            return true;
        });
    }

    private function advance(string $frequency, CarbonImmutable $date): CarbonImmutable
    {
        return match ($frequency) {
            'daily' => $date->addDay(),
            'weekly' => $date->addWeek(),
            'yearly' => $date->addYearNoOverflow(),
            default => $date->addMonthNoOverflow(), // monthly
        };
    }
}
