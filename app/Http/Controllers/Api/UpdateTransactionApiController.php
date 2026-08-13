<?php

namespace App\Http\Controllers\Api;

use App\Actions\ResolveTransactionBudgetLink;
use App\Actions\SaveTransaction;
use App\DTO\TransactionData;
use App\Http\Controllers\Controller;
use App\Http\Requests\TransactionUpdateRequest;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;

class UpdateTransactionApiController extends Controller
{
    public function __invoke(TransactionUpdateRequest $request, int $transaction): JsonResponse
    {
        // User-scoped lookup: another user's id resolves to 404, never 403.
        $transaction = Transaction::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($transaction);

        // Merge the patch over the current row so the shared save action gets
        // a complete TransactionData (its fields are required, not nullable).
        $data = TransactionData::from(array_merge(
            [
                'balance_id' => $transaction->balance_id,
                'budget_id' => $transaction->budget_id,
                'budget_item_id' => $transaction->budget_item_id,
                'category_id' => $transaction->category_id,
                'type' => $transaction->type->value,
                'date' => $transaction->date->toDateString(),
                'amount' => $transaction->amount,
                'description' => $transaction->description,
                'transfer_group_id' => $transaction->transfer_group_id,
            ],
            $request->validated(),
        ));

        // Explicit null + null = detach (spec §8.1): when the caller
        // deliberately clears BOTH budget fields, save the nulls as-is and
        // skip the auto-link resolver. Without this, a category-only change
        // keeps the stale item (item wins over category), and explicit nulls
        // resurrect the active budget onto a historical row — both corrupt
        // budget actuals. The data-fix reclassification depends on this.
        $validated = $request->validated();
        $explicitDetach = array_key_exists('budget_id', $validated)
            && array_key_exists('budget_item_id', $validated)
            && $validated['budget_id'] === null
            && $validated['budget_item_id'] === null;

        if ($explicitDetach) {
            $transaction->budget()->dissociate();
            $transaction->budgetItem()->dissociate();
            $data->budget_id = null;
            $data->budget_item_id = null;
        } else {
            ['budget_id' => $budgetId, 'budget_item_id' => $budgetItemId] = ResolveTransactionBudgetLink::run(
                $request->user()->id,
                $data->category_id,
                $data->budget_id,
                $data->budget_item_id,
            );

            $data->budget_id = $budgetId;
            $data->budget_item_id = $budgetItemId;
        }

        SaveTransaction::run($transaction, $data);

        return response()->json(
            TransactionData::from($transaction->load(['balance', 'category'])),
        );
    }
}
