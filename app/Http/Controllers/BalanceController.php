<?php

namespace App\Http\Controllers;

use App\Actions\SaveBalance;
use App\Http\Requests\BalanceSaveRequest;
use App\Models\Balance;
use Illuminate\Http\RedirectResponse;

class BalanceController extends Controller
{
    public function update(Balance $balance, BalanceSaveRequest $request): RedirectResponse
    {
        SaveBalance::run($balance, $request->getData());

        return back()->with('success', __('app.updated_data', ['data' => ['app.balance']]));
    }
}
