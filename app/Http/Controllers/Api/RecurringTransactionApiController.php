<?php

namespace App\Http\Controllers\Api;

use App\Actions\DeleteRecurringTransaction;
use App\Actions\SaveRecurringTransaction;
use App\DTO\RecurringTransactionData;
use App\Http\Controllers\Controller;
use App\Http\Requests\RecurringTransactionSaveRequest;
use App\Models\RecurringTransaction;
use App\Queries\RecurringTransactionQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

/**
 * Full CRUD for /api/recurring-transactions. Mirrors the sibling API
 * controllers: server-side user scoping via where('user_id', ...)->findOrFail(),
 * form request validation, and delegation to the shared save/delete actions.
 */
class RecurringTransactionApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $recurrings = RecurringTransactionQuery::make($request->all(), ['balance', 'category'])
            ->forUser($request->user()->id)
            ->result();

        $isPaginated = $request->boolean('is_paginate');

        $collection = $isPaginated
            ? RecurringTransactionData::collect($recurrings, PaginatedDataCollection::class)
            : RecurringTransactionData::collect($recurrings);

        return response()->json($collection);
    }

    public function store(RecurringTransactionSaveRequest $request): JsonResponse
    {
        $recurring = SaveRecurringTransaction::run(new RecurringTransaction, $request->getData());

        return response()->json(
            RecurringTransactionData::from($recurring->fresh()->load(['balance', 'category'])),
            201,
        );
    }

    public function show(Request $request, int $recurringTransaction): JsonResponse
    {
        $recurring = RecurringTransaction::query()
            ->with(['balance', 'category'])
            ->where('user_id', $request->user()->id)
            ->findOrFail($recurringTransaction);

        return response()->json(RecurringTransactionData::from($recurring));
    }

    public function update(RecurringTransactionSaveRequest $request, int $recurringTransaction): JsonResponse
    {
        $recurring = RecurringTransaction::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($recurringTransaction);

        // Merge the payload over the current row so the shared save action
        // gets a complete RecurringTransactionData (its fields are required,
        // not nullable).
        $data = RecurringTransactionData::from(array_merge(
            [
                'type' => $recurring->type->value,
                'balance_id' => $recurring->balance_id,
                'category_id' => $recurring->category_id,
                'amount' => $recurring->amount,
                'description' => $recurring->description,
                'frequency' => $recurring->frequency,
                'start_date' => $recurring->start_date->toDateString(),
                'end_date' => $recurring->end_date?->toDateString(),
                'next_run_date' => $recurring->next_run_date->toDateString(),
                'is_active' => $recurring->is_active,
            ],
            $request->validated(),
        ));

        SaveRecurringTransaction::run($recurring, $data);

        return response()->json(
            RecurringTransactionData::from($recurring->fresh()->load(['balance', 'category'])),
        );
    }

    public function destroy(Request $request, int $recurringTransaction): JsonResponse
    {
        $recurring = RecurringTransaction::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($recurringTransaction);

        DeleteRecurringTransaction::run($recurring);

        return response()->json(null, 204);
    }
}
