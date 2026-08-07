<?php

namespace App\Http\Controllers;

use App\Actions\DeleteBalance;
use App\Actions\SaveBalance;
use App\Actions\SetPrimaryBalance;
use App\DTO\BalanceData;
use App\DTO\TransactionData;
use App\Http\Requests\BalanceSaveRequest;
use App\Models\Balance;
use App\Queries\BalanceQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelData\PaginatedDataCollection;

class BalanceController extends Controller
{
    public function index(Request $request): Response
    {
        $balances = BalanceQuery::make($request->all())
            ->forUser(Auth::id())
            ->paginate();

        return Inertia::render('BalanceList', [
            'balances' => BalanceData::collect($balances, PaginatedDataCollection::class),
        ]);
    }

    public function show(Balance $balance, Request $request): Response
    {
        $transactions = $balance->transactions()
            ->with(['category'])
            ->latest('date')
            ->paginate(25);

        return Inertia::render('BalanceDetail', [
            'balance' => BalanceData::from($balance),
            'transactions' => TransactionData::collect($transactions, PaginatedDataCollection::class),
        ]);
    }

    public function store(BalanceSaveRequest $request): RedirectResponse
    {
        SaveBalance::run(new Balance, $request->getData());

        return back()->with('success', __('app.created_data', ['data' => __('app.balance')]));
    }

    public function update(Balance $balance, BalanceSaveRequest $request): RedirectResponse
    {
        SaveBalance::run($balance, $request->getData());

        return back()->with('success', __('app.updated_data', ['data' => __('app.balance')]));
    }

    public function destroy(Balance $balance): RedirectResponse
    {
        DeleteBalance::run($balance);

        return back()->with('success', __('app.deleted_data', ['data' => __('app.balance')]));
    }

    public function setPrimary(Balance $balance): RedirectResponse
    {
        SetPrimaryBalance::run($balance);

        return back()->with('success', __('app.updated_data', ['data' => __('app.balance')]));
    }
}
