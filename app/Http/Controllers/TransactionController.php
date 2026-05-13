<?php

namespace App\Http\Controllers;

use App\Actions\DeleteTransaction;
use App\Actions\SaveTransaction;
use App\Actions\StoreTransactions;
use App\DTO\TransactionData;
use App\Http\Requests\TransactionSaveRequest;
use App\Http\Requests\TransactionsSaveRequest;
use App\Models\Transaction;
use App\Queries\TransactionQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelData\PaginatedDataCollection;

class TransactionController extends Controller
{
    public function index(Request $request): Response
    {
        $transactions = TransactionQuery::make($request->all())->paginate();

        return Inertia::render('TransactionList', [
            'transactions' => TransactionData::collect($transactions, PaginatedDataCollection::class),
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
}
