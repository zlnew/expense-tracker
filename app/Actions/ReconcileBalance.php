<?php

namespace App\Actions;

use App\DTO\TransactionData;
use App\Enums\CategoryType;
use App\Models\Balance;
use App\Models\Category;
use App\Models\Transaction;
use Carbon\CarbonImmutable;

class ReconcileBalance extends Action
{
    public function __construct(
        private readonly Balance $balance,
        private readonly int $reconciledAmount,
        private readonly string $reconciledAt,
        private readonly bool $autoAdjust = false,
    ) {}

    public function handle(): ?Transaction
    {
        $parsed = CarbonImmutable::parse($this->reconciledAt);
        $reconciledTimestamp = $parsed->isToday() ? now() : $parsed->endOfDay();

        $adjustmentTransaction = null;

        if ($this->autoAdjust) {
            $diff = (int) $this->balance->final_amount - $this->reconciledAmount;

            if ($diff !== 0) {
                // diff > 0: recorded ledger is higher than actual statement -> money missing -> EXPENSE
                // diff < 0: recorded ledger is lower than actual statement -> extra money -> INCOME
                $type = $diff > 0 ? CategoryType::EXPENSE : CategoryType::INCOME;
                $amount = abs($diff);

                $category = Category::query()
                    ->where('user_id', $this->balance->user_id)
                    ->where('type', $type)
                    ->whereIn('name', ['Penyesuaian', 'Adjustment', 'Other', 'Lainnya'])
                    ->first();

                $dto = TransactionData::from([
                    'user_id' => $this->balance->user_id,
                    'balance_id' => $this->balance->id,
                    'category_id' => $category?->id,
                    'type' => $type->value,
                    'date' => $parsed->toDateString(),
                    'amount' => $amount,
                    'description' => 'Penyesuaian saldo rekonsiliasi',
                ]);

                $adjustmentTransaction = SaveTransaction::run(new Transaction, $dto);

                $this->balance->refresh();
            }
        }

        $this->balance->reconciled_amount = $this->reconciledAmount;
        $this->balance->reconciled_at = $reconciledTimestamp;
        $this->balance->save();

        return $adjustmentTransaction;
    }
}
