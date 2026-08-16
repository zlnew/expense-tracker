<?php

namespace App\Http\Controllers;

use App\DTO\TransactionData;
use App\Models\Budget;
use App\Queries\TransactionQuery;
use App\Support\BudgetActuals;
use App\Support\BudgetCycle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

/**
 * Web (session-auth) transactions read for BudgetDetail — the envelope-aware
 * companion to the frozen /api/transactions Sanctum contract.
 *
 * Returns the same TransactionQuery result as GetTransactionApiController
 * PLUS the envelope extras the budget page needs to compute truthful item
 * actuals client-side:
 *   fund.reserved — set-aside sums per budget item (cycle-windowed with the
 *                   same cutoff rule as the transaction list);
 *   fund.payout_transaction_ids — transactions to EXCLUDE from item actuals
 *                   (fund payouts are budget-exempt, 2026-08-16 spec).
 */
class BudgetTransactionsController extends Controller
{
    public function __invoke(Request $request, Budget $budget): JsonResponse
    {
        $user = $request->user();

        $data = TransactionQuery::make($request->all())
            ->forUser($user->id)
            ->result();

        $isPaginated = $request->boolean('is_paginate');

        $transactions = $isPaginated
            ? TransactionData::collect($data, PaginatedDataCollection::class)
            : TransactionData::collect($data);

        $payoutIds = BudgetActuals::payoutTransactionIds($user);

        $month = $request->input('month');
        $year = $request->input('year');

        if ($month !== null && $year !== null) {
            $reserved = BudgetActuals::reservedPerItemForCycleMonth($user, $budget, (int) $month, (int) $year);
        } else {
            [$start, $end] = $budget->is_active
                ? BudgetCycle::currentCycleRange($budget)
                : [$budget->period_start, $budget->period_end];

            $reserved = BudgetActuals::reservedPerItem($user, $budget, $start, $end);
        }

        return response()->json([
            'transactions' => $transactions,
            'fund' => [
                'reserved' => $reserved,
                'payout_transaction_ids' => $payoutIds,
            ],
        ]);
    }
}
