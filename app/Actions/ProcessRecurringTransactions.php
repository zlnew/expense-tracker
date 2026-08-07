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
            // Re-read under lock: another process (scheduler vs dashboard
            // catch-up) may have already advanced this schedule between our
            // due-query and now.
            $locked = RecurringTransaction::query()
                ->whereKey($recurring->id)
                ->lockForUpdate()
                ->first();

            if (! $locked || ! $locked->is_active) {
                return false;
            }

            // Re-check the due condition against the locked row, not the stale one.
            if ($locked->next_run_date->gt(now()->startOfDay())) {
                return false;
            }

            $runDate = $locked->next_run_date;

            SaveTransaction::run(new Transaction, TransactionData::from([
                'balance_id' => $locked->balance_id,
                'budget_id' => null,
                'budget_item_id' => null,
                'category_id' => $locked->category_id,
                'type' => $locked->type->value,
                'date' => $runDate,
                'amount' => $locked->amount,
                'description' => $locked->description,
            ]));

            $next = $this->advance($locked->frequency, $runDate);

            if ($locked->end_date && $next->greaterThan($locked->end_date)) {
                $locked->update(['is_active' => false]);

                return true;
            }

            $locked->update(['next_run_date' => $next]);

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
