<?php

namespace App\Actions;

use App\DTO\FundContributionData;
use App\DTO\TransactionData;
use App\Enums\CategoryType;
use App\Models\FundContribution;
use App\Models\SinkingFund;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pay a bill from the fund — one atomic call (spec D2):
 *
 * 1. lock the fund row (deterministic locking, same discipline as
 *    TransferBetweenAccounts); re-check the accumulated reserve under the lock;
 * 2. resolve the budget link via ResolveTransactionBudgetLink (PR #48) from
 *    the fund's category_id — budget actuals pick the bill up like any other
 *    category spend;
 * 3. create a REAL expense Transaction via SaveTransaction (type expense,
 *    user-chosen balance_id) — SaveTransaction calls SyncBalance, so the
 *    paying balance's final_amount drops for real;
 * 4. run CheckBudgetAlerts — same parity as the web transaction store;
 * 5. write a withdrawal ledger row carrying the new transaction_id (audit
 *    link fund → transaction);
 * 6. roll next_due forward (D6).
 *
 * Guard: amount > accumulated → 422 `insufficient_fund_reserve`.
 */
class PayFromFund extends Action
{
    public function __construct(
        private readonly SinkingFund $fund,
        private readonly FundContributionData $data,
    ) {}

    public function handle(): FundContribution
    {
        return DB::transaction(function () {
            // Lock the fund row and re-check the reserve under the lock —
            // the value read before the transaction may be stale.
            $locked = SinkingFund::query()
                ->whereKey($this->fund->id)
                ->lockForUpdate()
                ->firstOrFail();

            $accumulated = (int) $locked->contributions()
                ->selectRaw(
                    'COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE 0 END), 0) - COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE 0 END), 0) AS balance',
                    ['contribution', 'withdrawal'],
                )
                ->value('balance');

            if ($accumulated < $this->data->amount) {
                throw ValidationException::withMessages([
                    'amount' => __('insufficient_fund_reserve'),
                ]);
            }

            // 2. Auto budget-link from the fund's category (PR #48 resolver).
            ['budget_id' => $budgetId, 'budget_item_id' => $budgetItemId] = ResolveTransactionBudgetLink::run(
                $locked->user_id,
                $locked->category_id,
            );

            // 3. Real expense — SaveTransaction runs SyncBalance internally.
            $transaction = SaveTransaction::run(new Transaction, TransactionData::from([
                'balance_id' => $this->data->balance_id,
                'budget_id' => $budgetId,
                'budget_item_id' => $budgetItemId,
                'category_id' => $locked->category_id,
                'type' => CategoryType::EXPENSE->value,
                'date' => $this->data->date,
                'amount' => $this->data->amount,
                'description' => $this->data->description,
            ]));

            // 4. Budget alert parity with the web store path.
            CheckBudgetAlerts::run($locked->user, $transaction);

            // 5. Ledger withdrawal carrying the real transaction id.
            $contribution = $locked->contributions()->create([
                'user_id' => $locked->user_id,
                'type' => 'withdrawal',
                'amount' => $this->data->amount,
                'date' => $this->data->date,
                'transaction_id' => $transaction->id,
                'description' => $this->data->description,
            ]);

            // 6. Roll next_due anchored to the OLD due date (paying late
            // doesn't drift the cadence); null stays null.
            if ($locked->next_due !== null) {
                $locked->next_due = $locked->next_due->addMonthsNoOverflow($locked->due_interval_months);
                $locked->save();
            }

            return $contribution;
        });
    }
}
