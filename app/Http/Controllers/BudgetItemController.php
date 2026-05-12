<?php

namespace App\Http\Controllers;

use App\Actions\SaveBudgetItems;
use App\Http\Requests\BudgetItemsSaveRequest;
use App\Models\Budget;
use Illuminate\Http\RedirectResponse;

class BudgetItemController extends Controller
{
    public function bulkSave(Budget $budget, BudgetItemsSaveRequest $request): RedirectResponse
    {
        SaveBudgetItems::run($budget, $request->getData());

        return back()->with('success', __('app.saved_data', ['data' => 'app.budget_items']));
    }
}
