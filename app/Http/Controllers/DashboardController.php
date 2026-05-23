<?php

namespace App\Http\Controllers;

use App\Actions\GetBudgetProgress;
use App\Actions\GetExpenseBreakdown;
use App\Actions\GetMonthlySpendingTrend;
use App\Actions\GetRecentTransactions;
use App\Actions\GetSummaryCardsData;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $userId = Auth::id();

        $summaryCards = GetSummaryCardsData::run($userId);
        $budgetProgress = GetBudgetProgress::run($userId);
        $expenseBreakdown = GetExpenseBreakdown::run($userId);
        $monthlySpendingTrend = GetMonthlySpendingTrend::run($userId);
        $recentTransactions = GetRecentTransactions::run($userId);

        return Inertia::render('Dashboard', [
            'summary_cards' => $summaryCards,
            'budget_progress' => $budgetProgress,
            'expense_breakdown' => $expenseBreakdown,
            'monthly_spending_trend' => $monthlySpendingTrend,
            'recent_transactions' => $recentTransactions,
        ]);
    }
}
