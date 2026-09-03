<?php

namespace App\Mcp\Tools;

use App\Actions\DeleteTransaction;
use App\Models\Transaction;
use App\Models\User;

class DeleteTransactionTool implements ToolInterface
{
    public function name(): string
    {
        return 'delete_transaction';
    }

    public function description(): string
    {
        return 'Delete a transaction by ID. If the transaction is part of a transfer pair or a linked fund payout, both legs are deleted atomically and balances are resynced.';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'required' => ['transaction_id'],
            'properties' => [
                'transaction_id' => [
                    'type' => 'integer',
                    'description' => 'The ID of the transaction to delete',
                ],
            ],
        ];
    }

    public function execute(User $user, array $arguments): array
    {
        $id = (int) ($arguments['transaction_id'] ?? 0);
        $transaction = Transaction::query()
            ->where('user_id', $user->id)
            ->with(['balance', 'category'])
            ->find($id);

        if (! $transaction) {
            return [
                'content' => [['type' => 'text', 'text' => "Error: Transaction #{$id} not found or does not belong to user."]],
                'isError' => true,
            ];
        }

        $desc = $transaction->description ?: 'No description';
        $amountFmt = 'Rp '.number_format($transaction->amount, 0, ',', '.');
        $balanceName = $transaction->balance?->name ?? 'Account';
        $isTransfer = ! empty($transaction->transfer_group_id);

        DeleteTransaction::run($transaction);

        $msg = "Transaction #{$id} ({$amountFmt}, '{$desc}', {$balanceName}) was deleted successfully.";
        if ($isTransfer) {
            $msg .= ' Paired transfer/fund leg was also removed, and balances were resynchronized.';
        }

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $msg,
                ],
            ],
        ];
    }
}
