<?php

namespace App\Mcp\Tools;

use App\Actions\SaveFundContribution;
use App\DTO\FundContributionData;
use App\Models\SinkingFund;
use App\Models\User;
use Carbon\CarbonImmutable;

class CreateFundContributionTool implements ToolInterface
{
    public function name(): string
    {
        return 'create_fund_contribution';
    }

    public function description(): string
    {
        return 'Record a contribution (set-aside) into a sinking fund reserve. Reserves money from the fund\'s source account without creating an active expense.';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'required' => ['fund_id', 'amount'],
            'properties' => [
                'fund_id' => [
                    'type' => 'integer',
                    'description' => 'The ID of the sinking fund to contribute to',
                ],
                'amount' => [
                    'type' => 'integer',
                    'description' => 'Contribution amount in IDR',
                ],
                'date' => [
                    'type' => 'string',
                    'description' => 'Contribution date in YYYY-MM-DD (defaults to today)',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional note',
                ],
            ],
        ];
    }

    public function execute(User $user, array $arguments): array
    {
        $fundId = (int) ($arguments['fund_id'] ?? 0);
        $amount = (int) ($arguments['amount'] ?? 0);
        $dateStr = ! empty($arguments['date']) ? $arguments['date'] : now()->toDateString();
        $description = $arguments['description'] ?? 'Fund contribution';

        if ($amount <= 0) {
            return [
                'content' => [['type' => 'text', 'text' => 'Error: Contribution amount must be greater than zero.']],
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
            type: 'contribution',
            amount: $amount,
            date: CarbonImmutable::parse($dateStr),
            description: $description,
            balance_id: $fund->from_balance_id,
        );

        $contribution = SaveFundContribution::run($fund, $dto);

        $amountFmt = 'Rp '.number_format($amount, 0, ',', '.');
        $msg = "Successfully contributed {$amountFmt} to '{$fund->name}':\n"
            ."- Contribution ID: #{$contribution->id}\n"
            ."- Date: {$dateStr}";

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
