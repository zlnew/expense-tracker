<?php

namespace App\Http\Controllers\Api;

use App\DTO\CategoryData;
use App\Http\Controllers\Controller;
use App\Queries\CategoryQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryApiController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $categories = CategoryQuery::make($request->all())
            ->forUser($request->user()->id)
            ->get();

        return response()->json(CategoryData::collect($categories));
    }
}
