<?php

namespace App\Http\Controllers\Api;

use App\Actions\DeleteBalance;
use App\Actions\SaveBalance;
use App\Actions\TransferBetweenAccounts;
use App\DTO\BalanceData;
use App\Http\Controllers\Controller;
use App\Http\Requests\BalanceSaveRequest;
use App\Http\Requests\TransferBetweenAccountsRequest;
use App\Models\Balance;
use App\Queries\BalanceQuery;
use App\Support\BalancePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BalanceApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $balances = BalanceQuery::make($request->all())
            ->forUser($request->user()->id)
            ->get();

        return response()->json(BalancePresenter::collect($balances));
    }

    public function store(BalanceSaveRequest $request): JsonResponse
    {
        $balance = new Balance;

        SaveBalance::run($balance, $request->getData());

        return response()->json(BalancePresenter::fromModel($balance->fresh()), 201);
    }

    public function show(Request $request, int $balance): JsonResponse
    {
        $balance = Balance::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($balance);

        return response()->json(BalancePresenter::fromModel($balance));
    }

    public function update(BalanceSaveRequest $request, int $balance): JsonResponse
    {
        $balance = Balance::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($balance);

        // Merge the payload over the current row so the shared save action
        // gets a complete BalanceData (its fields are required, not nullable).
        $data = BalanceData::from(array_merge(
            [
                'name' => $balance->name,
                'description' => $balance->description,
                'initial_amount' => $balance->initial_amount,
            ],
            $request->validated(),
        ));

        SaveBalance::run($balance, $data);

        return response()->json(BalancePresenter::fromModel($balance->fresh()));
    }

    public function destroy(Request $request, int $balance): JsonResponse
    {
        $balance = Balance::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($balance);

        DeleteBalance::run($balance);

        return response()->json(null, 204);
    }

    public function transfer(TransferBetweenAccountsRequest $request): JsonResponse
    {
        TransferBetweenAccounts::run($request->getData());

        return response()->json(['message' => 'Transfer completed'], 200);
    }
}
