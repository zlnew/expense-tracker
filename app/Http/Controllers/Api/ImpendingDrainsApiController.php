<?php

namespace App\Http\Controllers\Api;

use App\Actions\GetImpendingDrains;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImpendingDrainsApiController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        // window is inclusive days ahead; default 60, min 1, max 365.
        $window = (int) $request->integer('window', 60);
        $window = max(1, min(365, $window));

        $data = GetImpendingDrains::run($request->user()->id, $window);

        return response()->json($data);
    }
}
