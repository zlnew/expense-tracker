<?php

namespace App\Http\Controllers;

use App\Actions\DeleteBudget;
use App\Actions\SaveBudget;
use App\Actions\SetActiveBudget;
use App\DTO\BudgetData;
use App\DTO\CategoryData;
use App\Http\Requests\BudgetSaveRequest;
use App\Models\Budget;
use App\Models\Category;
use App\Queries\BudgetQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelData\PaginatedDataCollection;

class BudgetController extends Controller
{
    public function index(Request $request): Response
    {
        $budgets = BudgetQuery::make($request->all())->paginate();

        return Inertia::render('BudgetList', [
            'budgets' => BudgetData::collect($budgets, PaginatedDataCollection::class),
        ]);
    }

    public function create(): Response
    {
        $categories = Category::all();

        return Inertia::render('BudgetCreate', [
            'categories' => CategoryData::collect($categories),
        ]);
    }

    public function edit(Budget $budget): Response
    {
        $budget->load([
            'expenses.category',
            'incomes.category',
        ]);

        return Inertia::render('BudgetEdit', [
            'budget' => BudgetData::from($budget),
        ]);
    }

    public function show(Budget $budget): Response
    {
        $budget->load([
            'expenses.category',
            'incomes.category',
            'transactions.category',
        ]);

        return Inertia::render('BudgetDetail', [
            'budget' => BudgetData::from($budget),
        ]);
    }

    public function store(BudgetSaveRequest $request): RedirectResponse
    {
        /** @var Budget $budget */
        $budget = SaveBudget::run(new Budget, $request->getData());

        return redirect()
            ->route('budgets.show', $budget)
            ->with('success', __('app.created_data', ['data' => __('app.budget')]));
    }

    public function update(Budget $budget, BudgetSaveRequest $request): RedirectResponse
    {
        SaveBudget::run($budget, $request->getData());

        return back()->with('success', __('app.updated_data', ['data' => __('app.budget')]));
    }

    public function destroy(Budget $budget): RedirectResponse
    {
        DeleteBudget::run($budget);

        return back()->with('success', __('app.deleted_data', ['data' => __('app.budget')]));
    }

    public function setActive(Budget $budget): RedirectResponse
    {
        SetActiveBudget::run($budget);

        return back()->with('success', __('app.updated_data', ['data' => __('app.budget')]));
    }
}
