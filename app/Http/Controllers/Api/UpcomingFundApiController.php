<?php

namespace App\Http\Controllers\Api;

use App\Actions\GetFundProgress;
use App\Actions\ListUpcomingDues;
use App\DTO\CategoryData;
use App\DTO\FundData;
use App\Http\Controllers\Controller;
use App\Models\SinkingFund;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UpcomingFundApiController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $horizon = max(1, (int) $request->integer('horizon', 60));

        $rows = ListUpcomingDues::run($request->user()->id, $horizon);

        $items = collect($rows)->map(function (array $row) {
            /** @var SinkingFund $fund */
            $fund = $row['fund'];

            $data = FundData::from(array_merge(
                $fund->only([
                    'id',
                    'user_id',
                    'name',
                    'target_amount',
                    'cadence',
                    'contribution_amount',
                    'category_id',
                    'next_due',
                    'due_interval_months',
                    'notes',
                ]),
                GetFundProgress::run($fund),
                [
                    'next_due' => $fund->next_due?->toDateString(),
                    'category' => $fund->category ? CategoryData::from($fund->category) : null,
                ],
            ))->toArray();

            $data['days_until_due'] = $row['days_until_due'];
            $data['projected_shortfall'] = $row['projected_shortfall'];

            return $data;
        });

        return response()->json($items->values()->all());
    }
}
