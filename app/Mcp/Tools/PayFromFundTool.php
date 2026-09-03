<?php

namespace App\Mcp\Tools;

use App\Actions\PayFromFund;
use App\DTO\FundContributionData;
use App\Models\SinkingFund;
use App\Models\User;
use Carbon\CarbonImmutable;

class PayFromFundTool implements ToolInterface
{
    public function name(): string
    {
        return 'pay_from_fund';
    }

    public function description(): string
    {
        return 'Pay an expense from a sinking fund reserve. Creates a real expense transaction linked to the fund and rolls the next due date forward.';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'required' => ['fund_id', 'amount', 'description'],
            'properties' => [
                'fund_id' => [
                    'type' => 'integer',
                    'description' => 'The ID of the sinking fund to pay from',
                ],
                'amount' => [
                    'type' => 'integer',
                    'description' => 'Amount to withdraw and spend in IDR',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Reason / description of the expense (e.g. "Ganti oli mobil")',
                ],
                'date' => [
                    'type' => 'string',
                    'description' => 'Date of payment (YYYY-MM-DD, defaults to today)',
                ],
            ],
        ];
    }

    public function execute(User $user, array $arguments): array
    {
        $fundId = (int) ($arguments['fund_id'] ?? 0);
        $amount = (int) ($arguments['amount'] ?? 0);
        $description = $arguments['description'] ?? '';
        $dateStr = ! empty($arguments['date']) ? $arguments['date'] : now()->toDateString();

        if ($amount <= 0) {
            return [
                'content' => [['type' => 'text', 'text' => 'Error: Amount must be greater than zero.']],
                'isError' => true,
            ];
        }

        $fund = SinkingFund::query()->where('user_id', $user->id)->find($fundId);
        if (! $fund) {
            return [
                'content' => [['type' => 'text', 'text' => "Error: Sinking fund #{$fundId} not found or does not belong to user."]],
                'isError' => true,
            ];
        }

        $dto = new FundContributionData(
            fund_id: $fund->id,
            user_id: $user->id,
            type: 'withdrawal',
            amount: $amount,
            date: CarbonImmutable::parse($dateStr),
            description: $description,
            balance_id: $fund->from_balance_id,
        );

        try {
            $withdrawal = PayFromFund::run($fund, $dto);

            $amountFmt = 'Rp '.number_format($amount, 0, ',', '.');
            $msg = "Successfully paid {$amountFmt} from fund '{$fund->name}':\n"
                ."- Linked Transaction ID: #{$withdrawal->transaction_id}\n"
                ."- Next Due Date: {$fund->fresh()->next_due?->toDateString()}\n"
                ."- Description: {$description}";

            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $msg,
                    ],
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'content' => [['type' => 'text', 'text' => 'Error paying from fund: '.$e->getMessage()]],
                'isError' => true,
            ];
        }
    }
}
