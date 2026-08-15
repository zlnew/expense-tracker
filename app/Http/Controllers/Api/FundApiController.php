<?php

namespace App\Http\Controllers\Api;

use App\Actions\DeleteFund;
use App\Actions\GetFundProgress;
use App\Actions\PayFromFund;
use App\Actions\SaveFund;
use App\Actions\SaveFundContribution;
use App\DTO\CategoryData;
use App\DTO\FundData;
use App\Http\Controllers\Controller;
use App\Http\Requests\FundContributionRequest;
use App\Http\Requests\FundSaveRequest;
use App\Http\Requests\FundUpdateRequest;
use App\Http\Requests\FundWithdrawalRequest;
use App\Models\SinkingFund;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FundApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $funds = SinkingFund::query()
            ->with('category')
            ->where('user_id', $request->user()->id)
            ->orderBy('name')
            ->get()
            ->map(fn (SinkingFund $fund) => $this->withProgress($fund));

        return response()->json(FundData::collect($funds));
    }

    public function store(FundSaveRequest $request): JsonResponse
    {
        $fund = SaveFund::run(new SinkingFund, $request->getData());

        return response()->json(
            $this->withProgress($fund->load('category')),
            201,
        );
    }

    public function update(FundUpdateRequest $request, int $fund): JsonResponse
    {
        $fund = SinkingFund::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($fund);

        $data = FundData::from(array_merge(
            [
                'name' => $fund->name,
                'target_amount' => $fund->target_amount,
                'cadence' => $fund->cadence,
                'contribution_amount' => $fund->contribution_amount,
                'category_id' => $fund->category_id,
                'next_due' => $fund->next_due?->toDateString(),
                'due_interval_months' => $fund->due_interval_months,
                'notes' => $fund->notes,
            ],
            $request->validated(),
        ));

        SaveFund::run($fund, $data);

        return response()->json($this->withProgress($fund->load('category')));
    }

    public function destroy(Request $request, int $fund): JsonResponse
    {
        $fund = SinkingFund::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($fund);

        DeleteFund::run($fund);

        return response()->json(null, 204);
    }

    public function storeContribution(FundContributionRequest $request, int $fund): JsonResponse
    {
        $fund = SinkingFund::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($fund);

        $contribution = SaveFundContribution::run($fund, $request->getData());

        return response()->json($this->withProgress($fund->load('category')), 201);
    }

    public function storeWithdrawal(FundWithdrawalRequest $request, int $fund): JsonResponse
    {
        $fund = SinkingFund::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($fund);

        PayFromFund::run($fund, $request->getData());

        return response()->json($this->withProgress($fund->load('category')), 201);
    }

    private function withProgress(SinkingFund $fund): FundData
    {
        return FundData::from(array_merge(
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
        ));
    }
}
