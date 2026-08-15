<?php

namespace App\Http\Controllers;

use App\Actions\GetBudgetProgress;
use App\Actions\GetExpenseBreakdown;
use App\Actions\GetMonthlySpendingTrend;
use App\Actions\GetRecentTransactions;
use App\Actions\GetSummaryCardsData;
use App\Actions\ProcessRecurringTransactions;
use App\Models\RecurringTransaction;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $userId = Auth::id();

        // Lazy fallback: if the host never runs the scheduler, catch up any
        // due recurrings on the user's first visit (cheap query when none).
        if (RecurringTransaction::where('user_id', $userId)
            ->where('is_active', true)
            ->whereDate('next_run_date', '<=', now()->toDateString())
            ->exists()) {
            ProcessRecurringTransactions::run($userId);
        }

        $summaryCards = GetSummaryCardsData::run($userId);
        $budgetProgress = GetBudgetProgress::run($userId);
        $recentTransactions = GetRecentTransactions::run($userId);

        return Inertia::render('Dashboard', [
            'summary_cards' => $summaryCards,
            'budget_progress' => $budgetProgress,
            // Heavy chart payloads are deferred: the dashboard's critical info
            // (balances, budget status) renders immediately, charts stream in
            // behind skeletons on first visit.
            'expense_breakdown' => Inertia::defer(fn () => GetExpenseBreakdown::run($userId)),
            'monthly_spending_trend' => Inertia::defer(fn () => GetMonthlySpendingTrend::run($userId)),
            'recent_transactions' => $recentTransactions,
        ]);
    }
}
