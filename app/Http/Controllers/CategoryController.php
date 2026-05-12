<?php

namespace App\Http\Controllers;

use App\Actions\DeleteCategory;
use App\Actions\SaveCategory;
use App\DTO\CategoryData;
use App\Enums\CategoryType;
use App\Http\Requests\CategorySaveRequest;
use App\Models\Category;
use App\Queries\CategoryQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelData\PaginatedDataCollection;

class CategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $categories = CategoryQuery::make($request->all())->paginate();
        $types = CategoryType::cases();

        return Inertia::render('CategoryList', [
            'categories' => CategoryData::collect($categories, PaginatedDataCollection::class),
            'types' => $types,
        ]);
    }

    public function store(CategorySaveRequest $request): RedirectResponse
    {
        SaveCategory::run(new Category, $request->getData());

        return back()->with('success', __('app.created_data', ['data' => 'app.category']));
    }

    public function update(Category $category, CategorySaveRequest $request): RedirectResponse
    {
        SaveCategory::run($category, $request->getData());

        return back()->with('success', __('app.updated_data', ['data' => 'app.category']));
    }

    public function destroy(Category $category): RedirectResponse
    {
        DeleteCategory::run($category);

        return back()->with('success', __('app.deleted_data', ['data' => 'app.category']));
    }
}
