<?php

namespace App\Http\Controllers\Api;

use App\Actions\DeleteCategory;
use App\Actions\SaveCategory;
use App\DTO\CategoryData;
use App\Http\Controllers\Controller;
use App\Http\Requests\CategorySaveRequest;
use App\Models\Category;
use App\Queries\CategoryQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $categories = CategoryQuery::make($request->all())
            ->forUser($request->user()->id)
            ->get();

        return response()->json(CategoryData::collect($categories));
    }

    public function store(CategorySaveRequest $request): JsonResponse
    {
        $category = new Category;

        SaveCategory::run($category, $request->getData());

        return response()->json(CategoryData::from($category->fresh()), 201);
    }

    public function update(CategorySaveRequest $request, int $category): JsonResponse
    {
        $category = Category::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($category);

        // Merge the payload over the current row so the shared save action
        // gets a complete CategoryData (its fields are required, not nullable).
        $data = CategoryData::from(array_merge(
            [
                'type' => $category->type->value,
                'name' => $category->name,
            ],
            $request->validated(),
        ));

        SaveCategory::run($category, $data);

        return response()->json(CategoryData::from($category->fresh()));
    }

    public function destroy(Request $request, int $category): JsonResponse
    {
        $category = Category::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($category);

        DeleteCategory::run($category);

        return response()->json(null, 204);
    }
}
