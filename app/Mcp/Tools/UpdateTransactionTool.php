<?php

namespace App\Mcp\Tools;

use App\Actions\CheckBudgetAlerts;
use App\Actions\GetBalanceInsight;
use App\Actions\ResolveTransactionBudgetLink;
use App\Actions\SaveTransaction;
use App\Actions\SyncBalance;
use App\DTO\TransactionData;
use App\Enums\CategoryType;
use App\Models\Balance;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class UpdateTransactionTool implements ToolInterface
{
    public function name(): string
    {
        return 'update_transaction';
    }

    public function description(): string
    {
        return 'Update an existing transaction (amount, type, category, balance, date, description). Automatically resynchronizes account balances and updates budget linkages. For transfer pairs, updating amount, date, or description synchronizes both legs.';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'required' => ['transaction_id'],
            'properties' => [
                'transaction_id' => [
                    'type' => 'integer',
                    'description' => 'The ID of the transaction to update',
                ],
                'amount' => [
                    'type' => 'integer',
                    'description' => 'New transaction amount in IDR (must be greater than 0)',
                ],
                'type' => [
                    'type' => 'string',
                    'enum' => ['expense', 'income'],
                    'description' => 'Type of transaction: expense or income',
                ],
                'category_id' => [
                    'type' => 'integer',
                    'description' => 'New ID of the category',
                ],
                'balance_id' => [
                    'type' => 'integer',
                    'description' => 'New ID of the balance / account',
                ],
                'date' => [
                    'type' => 'string',
                    'description' => 'New transaction date in YYYY-MM-DD format',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'New description or notes for the transaction',
                ],
            ],
        ];
    }

    public function execute(User $user, array $arguments): array
    {
        $id = (int) ($arguments['transaction_id'] ?? 0);
        $transaction = Transaction::query()
            ->where('user_id', $user->id)
            ->with(['balance', 'category', 'budget', 'budgetItem'])
            ->find($id);

        if (! $transaction) {
            return [
                'content' => [['type' => 'text', 'text' => "Error: Transaction #{$id} not found or does not belong to user."]],
                'isError' => true,
            ];
        }

        if (array_key_exists('amount', $arguments)) {
            $amount = (int) $arguments['amount'];
            if ($amount <= 0) {
                return [
                    'content' => [['type' => 'text', 'text' => 'Error: Amount must be greater than zero.']],
                    'isError' => true,
                ];
            }
        } else {
            $amount = (int) $transaction->amount;
        }

        $typeStr = $arguments['type'] ?? $transaction->type->value;
        $type = CategoryType::tryFrom($typeStr) ?? $transaction->type;

        $balanceId = array_key_exists('balance_id', $arguments)
            ? (int) $arguments['balance_id']
            : (int) $transaction->balance_id;

        $balance = Balance::query()->where('user_id', $user->id)->find($balanceId);
        if (! $balance) {
            return [
                'content' => [['type' => 'text', 'text' => "Error: Balance with ID {$balanceId} not found or does not belong to user."]],
                'isError' => true,
            ];
        }

        $categoryId = array_key_exists('category_id', $arguments)
            ? ($arguments['category_id'] !== null ? (int) $arguments['category_id'] : null)
            : $transaction->category_id;

        $category = null;
        if ($categoryId !== null) {
            $category = Category::query()->where('user_id', $user->id)->find($categoryId);
            if (! $category) {
                return [
                    'content' => [['type' => 'text', 'text' => "Error: Category with ID {$categoryId} not found or does not belong to user."]],
                    'isError' => true,
                ];
            }
        }

        $dateStr = ! empty($arguments['date']) ? $arguments['date'] : $transaction->date->toDateString();
        $date = CarbonImmutable::parse($dateStr);

        $description = array_key_exists('description', $arguments)
            ? $arguments['description']
            : $transaction->description;

        // Handle transfer pair synchronization if applicable
        if (! empty($transaction->transfer_group_id)) {
            $pairedTransactions = Transaction::query()
                ->where('transfer_group_id', $transaction->transfer_group_id)
                ->where('user_id', $user->id)
                ->get();

            DB::transaction(function () use ($pairedTransactions, $amount, $date, $description) {
                foreach ($pairedTransactions as $pt) {
                    $pt->fill([
                        'amount' => $amount,
                        'date' => $date,
                        'description' => $description,
                    ]);
                    $pt->save();
                    SyncBalance::run($pt->balance_id);
                }
            });

            $transaction->refresh();
        } else {
            $budgetId = $transaction->budget_id;
            $budgetItemId = $transaction->budget_item_id;

            if ($categoryId && $categoryId !== $transaction->category_id) {
                ['budget_id' => $budgetId, 'budget_item_id' => $budgetItemId] = ResolveTransactionBudgetLink::run(
                    $user->id,
                    $categoryId,
                    null,
                    null,
                );
            }

            $dto = TransactionData::from([
                'balance_id' => $balanceId,
                'category_id' => $categoryId,
                'type' => $type->value,
                'date' => $date,
                'amount' => $amount,
                'description' => $description,
                'budget_id' => $budgetId,
                'budget_item_id' => $budgetItemId,
            ]);

            $transaction = SaveTransaction::run($transaction, $dto);
        }

        CheckBudgetAlerts::run($user, $transaction);

        $fresh = $transaction->fresh(['balance', 'category']);
        $balanceInsight = GetBalanceInsight::run($fresh->balance_id);
        $amountFmt = 'Rp '.number_format($fresh->amount, 0, ',', '.');
        $realFmt = 'Rp '.number_format($balanceInsight['real'], 0, ',', '.');
        $catName = $fresh->category?->name ?? 'None';

        $text = "Transaction #{$fresh->id} updated successfully:\n"
            ."- Type: {$fresh->type->value}\n"
            ."- Amount: {$amountFmt}\n"
            ."- Category: {$catName}".($fresh->category_id ? " (ID: {$fresh->category_id})" : '')."\n"
            ."- Account: {$fresh->balance->name} (ID: {$fresh->balance->id})\n"
            ."- Date: {$fresh->date->toDateString()}\n"
            .($fresh->description ? "- Description: {$fresh->description}\n" : '')
            ."- Updated {$fresh->balance->name} Real Balance: {$realFmt}";

        if (! empty($fresh->transfer_group_id)) {
            $text .= "\n- Transfer pair: Paired transaction leg was also updated with the new amount/date/description.";
        }

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $text,
                ],
            ],
        ];
    }
}
