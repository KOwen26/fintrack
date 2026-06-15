<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoriesController extends Controller
{
    public function __construct(private readonly CategoryService $categoryService) {}

    public function index(Request $request): Response
    {
        $categories = Category::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('parent_id')
            ->with('children')
            ->get();

        return Inertia::render('categories/index', [
            'categories' => $categories,
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $this->categoryService->create($request->user(), $request->validated());

        return back()->with('message', 'Category created.');
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $this->authorize('update', $category);
        $this->categoryService->update($category, $request->validated());

        return back()->with('message', 'Category updated.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $this->authorize('delete', $category);
        $this->categoryService->softDelete($category);

        return back()->with('message', 'Category deleted.');
    }
}
