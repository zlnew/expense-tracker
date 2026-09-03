<?php

namespace App\Mcp\Tools;

use App\Actions\CheckBudgetAlerts;
use App\Actions\GetBalanceInsight;
use App\Actions\ResolveTransactionBudgetLink;
use App\Actions\SaveTransaction;
use App\DTO\TransactionData;
use App\Models\Balance;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;

class CreateTransactionTool implements ToolInterface
{
    public function name(): string
    {
        return 'create_transaction';
    }

    public function description(): string
    {
        return 'Record a new expense or income transaction. Automatically links to the active budget (if applicable) and synchronizes account balance.';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'required' => ['amount', 'type', 'category_id', 'balance_id'],
            'properties' => [
                'amount' => [
                    'type' => 'integer',
                    'description' => 'Transaction amount in IDR (integer without decimals/symbols, e.g. 35000 for Rp 35.000)',
                ],
                'type' => [
                    'type' => 'string',
                    'enum' => ['expense', 'income'],
                    'description' => 'Type of transaction: expense or income',
                ],
                'category_id' => [
                    'type' => 'integer',
                    'description' => 'ID of the category',
                ],
                'balance_id' => [
                    'type' => 'integer',
                    'description' => 'ID of the balance / account',
                ],
                'date' => [
                    'type' => 'string',
                    'description' => 'Transaction date in YYYY-MM-DD format (defaults to current date if omitted)',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Description or notes for the transaction',
                ],
            ],
        ];
    }

    public function execute(User $user, array $arguments): array
    {
        $amount = (int) ($arguments['amount'] ?? 0);
        if ($amount <= 0) {
            return [
                'content' => [['type' => 'text', 'text' => 'Error: Amount must be greater than zero.']],
                'isError' => true,
            ];
        }

        $type = $arguments['type'] ?? 'expense';
        $categoryId = (int) ($arguments['category_id'] ?? 0);
        $balanceId = (int) ($arguments['balance_id'] ?? 0);
        $dateStr = ! empty($arguments['date']) ? $arguments['date'] : now()->toDateString();
        $description = $arguments['description'] ?? null;

        $category = Category::query()->where('user_id', $user->id)->find($categoryId);
        if (! $category) {
            return [
                'content' => [['type' => 'text', 'text' => "Error: Category with ID {$categoryId} not found or does not belong to user."]],
                'isError' => true,
            ];
        }

        $balance = Balance::query()->where('user_id', $user->id)->find($balanceId);
        if (! $balance) {
            return [
                'content' => [['type' => 'text', 'text' => "Error: Balance with ID {$balanceId} not found or does not belong to user."]],
                'isError' => true,
            ];
        }

        ['budget_id' => $budgetId, 'budget_item_id' => $budgetItemId] = ResolveTransactionBudgetLink::run(
            $user->id,
            $categoryId,
            null,
            null,
        );

        $dto = TransactionData::from([
            'balance_id' => $balanceId,
            'category_id' => $categoryId,
            'type' => $type,
            'date' => CarbonImmutable::parse($dateStr),
            'amount' => $amount,
            'description' => $description,
            'budget_id' => $budgetId,
            'budget_item_id' => $budgetItemId,
        ]);

        $transaction = SaveTransaction::run(new Transaction, $dto);

        CheckBudgetAlerts::run($user, $transaction);

        $balanceInsight = GetBalanceInsight::run($balanceId);
        $amountFmt = 'Rp '.number_format($amount, 0, ',', '.');
        $realFmt = 'Rp '.number_format($balanceInsight['real'], 0, ',', '.');

        $text = "Transaction #{$transaction->id} created successfully:\n"
            ."- Type: {$type}\n"
            ."- Amount: {$amountFmt}\n"
            ."- Category: {$category->name} (ID: {$category->id})\n"
            ."- Account: {$balance->name} (ID: {$balance->id})\n"
            ."- Date: {$dateStr}\n"
            .($description ? "- Description: {$description}\n" : '')
            ."- Updated {$balance->name} Real Balance: {$realFmt}";

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
