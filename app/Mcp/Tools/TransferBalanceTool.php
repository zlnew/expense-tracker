<?php

namespace App\Mcp\Tools;

use App\Actions\TransferBetweenAccounts;
use App\DTO\TransferBetweenAccountsData;
use App\Models\Balance;
use App\Models\User;
use Carbon\CarbonImmutable;

class TransferBalanceTool implements ToolInterface
{
    public function name(): string
    {
        return 'transfer_balance';
    }

    public function description(): string
    {
        return 'Transfer money between two accounts. Creates an atomic linked transaction pair (expense from source, income to destination).';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'required' => ['from_balance_id', 'to_balance_id', 'amount'],
            'properties' => [
                'from_balance_id' => [
                    'type' => 'integer',
                    'description' => 'Source account ID',
                ],
                'to_balance_id' => [
                    'type' => 'integer',
                    'description' => 'Destination account ID',
                ],
                'amount' => [
                    'type' => 'integer',
                    'description' => 'Transfer amount in IDR',
                ],
                'date' => [
                    'type' => 'string',
                    'description' => 'Transfer date in YYYY-MM-DD (defaults to today)',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Transfer memo / note',
                ],
            ],
        ];
    }

    public function execute(User $user, array $arguments): array
    {
        $fromId = (int) ($arguments['from_balance_id'] ?? 0);
        $toId = (int) ($arguments['to_balance_id'] ?? 0);
        $amount = (int) ($arguments['amount'] ?? 0);
        $dateStr = ! empty($arguments['date']) ? $arguments['date'] : now()->toDateString();
        $description = $arguments['description'] ?? 'Account transfer';

        if ($amount <= 0) {
            return [
                'content' => [['type' => 'text', 'text' => 'Error: Transfer amount must be greater than zero.']],
                'isError' => true,
            ];
        }

        if ($fromId === $toId) {
            return [
                'content' => [['type' => 'text', 'text' => 'Error: Source and destination accounts cannot be the same.']],
                'isError' => true,
            ];
        }

        $source = Balance::query()->where('user_id', $user->id)->find($fromId);
        $dest = Balance::query()->where('user_id', $user->id)->find($toId);

        if (! $source || ! $dest) {
            return [
                'content' => [['type' => 'text', 'text' => 'Error: One or both accounts not found or do not belong to user.']],
                'isError' => true,
            ];
        }

        if ($source->final_amount < $amount) {
            $availFmt = 'Rp '.number_format($source->final_amount, 0, ',', '.');

            return [
                'content' => [['type' => 'text', 'text' => "Error: Insufficient funds in {$source->name}. Available: {$availFmt}."]],
                'isError' => true,
            ];
        }

        $dto = new TransferBetweenAccountsData(
            from_account_id: $fromId,
            to_account_id: $toId,
            date: CarbonImmutable::parse($dateStr),
            amount: $amount,
            description: $description,
        );

        TransferBetweenAccounts::run($dto);

        $amountFmt = 'Rp '.number_format($amount, 0, ',', '.');
        $msg = "Successfully transferred {$amountFmt} from {$source->name} to {$dest->name}.\n"
            ."- Date: {$dateStr}\n"
            ."- Memo: {$description}";

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
