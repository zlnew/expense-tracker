<?php

namespace App\Http\Controllers;

use App\Actions\DeleteRecurringTransaction;
use App\Actions\SaveRecurringTransaction;
use App\DTO\BalanceData;
use App\DTO\CategoryData;
use App\DTO\RecurringTransactionData;
use App\Http\Requests\RecurringTransactionSaveRequest;
use App\Models\Balance;
use App\Models\Category;
use App\Models\RecurringTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RecurringTransactionController extends Controller
{
    public function index(Request $request): Response
    {
        $recurrings = RecurringTransaction::query()
            ->where('user_id', Auth::id())
            ->with(['balance', 'category'])
            ->orderBy('is_active', 'desc')
            ->orderBy('next_run_date', 'asc')
            ->get();

        $balances = Balance::where('user_id', Auth::id())->get();
        $categories = Category::where('user_id', Auth::id())->get();

        return Inertia::render('RecurringList', [
            'recurrings' => RecurringTransactionData::collect($recurrings),
            'balances' => BalanceData::collect($balances),
            'categories' => CategoryData::collect($categories),
        ]);
    }

    public function store(RecurringTransactionSaveRequest $request): RedirectResponse
    {
        SaveRecurringTransaction::run(new RecurringTransaction, $request->getData());

        return back()->with('success', __('app.created_data', ['data' => __('app.recurring_transaction')]));
    }

    public function update(RecurringTransaction $recurringTransaction, RecurringTransactionSaveRequest $request): RedirectResponse
    {
        SaveRecurringTransaction::run($recurringTransaction, $request->getData());

        return back()->with('success', __('app.updated_data', ['data' => __('app.recurring_transaction')]));
    }

    public function destroy(RecurringTransaction $recurringTransaction): RedirectResponse
    {
        DeleteRecurringTransaction::run($recurringTransaction);

        return back()->with('success', __('app.deleted_data', ['data' => __('app.recurring_transaction')]));
    }
}
