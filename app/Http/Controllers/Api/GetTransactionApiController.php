<?php

namespace App\Http\Controllers\Api;

use App\DTO\TransactionData;
use App\Http\Controllers\Controller;
use App\Queries\TransactionQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

class GetTransactionApiController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = TransactionQuery::make($request->all())
            ->forUser($request->user()?->id)
            ->result();

        $isPaginated = $request->boolean('is_paginate');

        $collection = $isPaginated
            ? TransactionData::collect($data, PaginatedDataCollection::class)
            : TransactionData::collect($data);

        return response()->json($collection);
    }
}
