<?php

namespace App\Http\Controllers\Api;

use App\Actions\CheckBudgetAlerts;
use App\Actions\ResolveTransactionBudgetLink;
use App\Actions\SaveTransaction;
use App\DTO\TransactionData;
use App\Http\Controllers\Controller;
use App\Http\Requests\TransactionSaveRequest;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;

class StoreTransactionApiController extends Controller
{
    public function __invoke(TransactionSaveRequest $request): JsonResponse
    {
        $data = $request->getData();

        ['budget_id' => $budgetId, 'budget_item_id' => $budgetItemId] = ResolveTransactionBudgetLink::run(
            $request->user()->id,
            $data->category_id,
            $data->budget_id,
            $data->budget_item_id,
        );

        $data->budget_id = $budgetId;
        $data->budget_item_id = $budgetItemId;

        $transaction = SaveTransaction::run(new Transaction, $data);

        CheckBudgetAlerts::run($request->user(), $transaction);

        return response()->json(
            TransactionData::from($transaction->load(['balance', 'category'])),
            201,
        );
    }
}
