<?php

namespace App\Http\Controllers;

use App\Actions\DeleteTransaction;
use App\Actions\SaveTransaction;
use App\Actions\StoreTransactions;
use App\Actions\TransferBetweenAccounts;
use App\DTO\BalanceData;
use App\DTO\BudgetData;
use App\DTO\CategoryData;
use App\DTO\TransactionData;
use App\Http\Requests\TransactionSaveRequest;
use App\Http\Requests\TransactionsSaveRequest;
use App\Http\Requests\TransferBetweenAccountsRequest;
use App\Models\Balance;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Queries\TransactionQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelData\PaginatedDataCollection;

class TransactionController extends Controller
{
    public function index(Request $request): Response
    {
        $transactions = TransactionQuery::make($request->all(), ['balance', 'category'])
            ->forUser(Auth::id())
            ->paginate();

        $balances = Balance::where('user_id', Auth::id())->get();
        $budgets = Budget::where('user_id', Auth::id())->with('items.category')->get();
        $categories = Category::all();

        $primaryBalance = $balances->firstWhere('is_primary', true);
        $activeBudget = $budgets->firstWhere('is_active', true);

        return Inertia::render('TransactionList', [
            'transactions' => TransactionData::collect($transactions, PaginatedDataCollection::class),
            'balances' => BalanceData::collect($balances),
            'budgets' => BudgetData::collect($budgets),
            'categories' => CategoryData::collect($categories),
            'primaryBalanceId' => $primaryBalance?->id,
            'activeBudgetId' => $activeBudget?->id,
        ]);
    }

    public function store(TransactionSaveRequest $request): RedirectResponse
    {
        SaveTransaction::run(new Transaction, $request->getData());

        return back()->with('success', __('app.created_data', ['data' => __('app.transaction')]));
    }

    public function update(Transaction $transaction, TransactionSaveRequest $request): RedirectResponse
    {
        SaveTransaction::run($transaction, $request->getData());

        return back()->with('success', __('app.updated_data', ['data' => __('app.transaction')]));
    }

    public function destroy(Transaction $transaction): RedirectResponse
    {
        DeleteTransaction::run($transaction);

        return back()->with('success', __('app.deleted_data', ['data' => __('app.transaction')]));
    }

    public function bulkStore(TransactionsSaveRequest $request): RedirectResponse
    {
        StoreTransactions::run($request->getData());

        return back()->with('success', __('app.saved_data', ['data' => __('app.transactions')]));
    }

    public function transferBetweenAccounts(TransferBetweenAccountsRequest $request): RedirectResponse
    {
        TransferBetweenAccounts::run($request->getData());

        return back()->with('success', __('app.saved_data', ['data' => __('app.transactions')]));
    }
}
