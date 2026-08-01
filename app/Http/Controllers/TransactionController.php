<?php

namespace App\Http\Controllers;

use App\Actions\CheckBudgetAlerts;
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
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionController extends Controller
{
    public function index(Request $request): Response
    {
        $transactions = TransactionQuery::make($request->all(), ['balance', 'category'])
            ->forUser(Auth::id())
            ->paginate();

        $balances = Balance::where('user_id', Auth::id())->get();
        $budgets = Budget::where('user_id', Auth::id())->with('items.category')->get();
        $categories = Category::where('user_id', Auth::id())->get();

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

    /**
     * Export the current transaction filters as a CSV (for Excel / tax).
     */
    public function export(Request $request): StreamedResponse
    {
        $transactions = TransactionQuery::make($request->all(), ['balance', 'category'])
            ->forUser(Auth::id())
            ->get();

        $filename = 'transactions-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($transactions) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM so Excel opens the file with correct encoding.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Date',
                'Type',
                'Category',
                'Balance',
                'Amount',
                'Description',
            ]);

            foreach ($transactions as $transaction) {
                fputcsv($handle, [
                    $transaction->date?->format('Y-m-d'),
                    $transaction->type->value,
                    $transaction->category?->name ?? '',
                    $transaction->balance?->name ?? '',
                    $transaction->amount,
                    $transaction->description ?? '',
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function store(TransactionSaveRequest $request): RedirectResponse
    {
        $transaction = SaveTransaction::run(new Transaction, $request->getData());

        CheckBudgetAlerts::run($request->user(), $transaction);

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
