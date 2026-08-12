<?php

namespace App\Http\Controllers\Api;

use App\DTO\BalanceData;
use App\Http\Controllers\Controller;
use App\Queries\BalanceQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BalanceApiController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $balances = BalanceQuery::make($request->all())
            ->forUser($request->user()->id)
            ->get();

        return response()->json(BalanceData::collect($balances));
    }
}
