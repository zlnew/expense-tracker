<?php

namespace App\Http\Controllers;

use App\Actions\DeleteFund;
use App\Actions\GetFundProgress;
use App\Actions\PayFromFund;
use App\Actions\SaveFund;
use App\Actions\SaveFundContribution;
use App\DTO\BalanceData;
use App\DTO\CategoryData;
use App\DTO\FundData;
use App\Enums\CategoryType;
use App\Http\Requests\FundContributionRequest;
use App\Http\Requests\FundSaveRequest;
use App\Http\Requests\FundWithdrawalRequest;
use App\Models\Balance;
use App\Models\Category;
use App\Models\SinkingFund;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class FundController extends Controller
{
    public function index(Request $request): Response
    {
        $funds = SinkingFund::query()
            ->with(['category', 'sourceBalance'])
            ->where('user_id', Auth::id())
            ->orderBy('name')
            ->get()
            ->map(fn (SinkingFund $fund) => $this->withProgress($fund));

        $categories = Category::query()
            ->where('user_id', Auth::id())
            ->where('type', CategoryType::EXPENSE)
            ->orderBy('name')
            ->get();

        $balances = Balance::query()
            ->where('user_id', Auth::id())
            ->get();

        return Inertia::render('FundsList', [
            'funds' => FundData::collect($funds),
            'categories' => CategoryData::collect($categories),
            'balances' => BalanceData::collect($balances),
        ]);
    }

    public function store(FundSaveRequest $request): RedirectResponse
    {
        SaveFund::run(new SinkingFund, $request->getData());

        return back()->with('success', __('app.created_data', ['data' => __('app.fund')]));
    }

    public function update(SinkingFund $fund, FundSaveRequest $request): RedirectResponse
    {
        SaveFund::run($fund, $request->getData());

        return back()->with('success', __('app.updated_data', ['data' => __('app.fund')]));
    }

    public function destroy(SinkingFund $fund): RedirectResponse
    {
        DeleteFund::run($fund);

        return back()->with('success', __('app.deleted_data', ['data' => __('app.fund')]));
    }

    public function storeContribution(SinkingFund $fund, FundContributionRequest $request): RedirectResponse
    {
        SaveFundContribution::run($fund, $request->getData());

        return back()->with('success', __('app.created_data', ['data' => __('app.fund_contribution')]));
    }

    public function storeWithdrawal(SinkingFund $fund, FundWithdrawalRequest $request): RedirectResponse
    {
        PayFromFund::run($fund, $request->getData());

        return back()->with('success', __('app.created_data', ['data' => __('app.fund_withdrawal')]));
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
                'from_balance_id',
                'next_due',
                'due_interval_months',
                'notes',
            ]),
            GetFundProgress::run($fund),
            [
                'next_due' => $fund->next_due?->toDateString(),
                'category' => $fund->category ? CategoryData::from($fund->category) : null,
                'source_balance' => $fund->sourceBalance ? BalanceData::from($fund->sourceBalance) : null,
            ],
        ));
    }
}
