<?php

namespace App\Http\Controllers\Api;

use App\Actions\CheckBudgetAlerts;
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
        $transaction = SaveTransaction::run(new Transaction, $request->getData());

        CheckBudgetAlerts::run($request->user(), $transaction);

        return response()->json(
            TransactionData::from($transaction->load(['balance', 'category'])),
            201,
        );
    }
}
