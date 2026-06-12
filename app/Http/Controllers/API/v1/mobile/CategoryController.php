<?php

namespace App\Http\Controllers\API\v1\mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Category\StoreRequest;
use App\Http\Requests\Category\UpdateRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Traits\ApiResponse;
use App\Models\Products\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $categories = Category::withCount('items')
            ->where('status', 1)
            ->where('name', 'like', '%'.$request->term.'%')
            ->orderBy('name')
            ->get();

        return $this->success([
            'categories' => CategoryResource::collection($categories),
        ]);
    }

    public function store(StoreRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $category = Category::create([
            'name' => $validated['name'],
            'status' => true,
            'user_id' => auth()->user()->user_id,
        ]);

        return $this->created([
            'category' => new CategoryResource($category),
        ], 'Category created successfully!');
    }

    public function show(Category $category): JsonResponse
    {
        $category->loadCount('items')->load('items');

        return $this->success(new CategoryResource($category));
    }

    public function update(UpdateRequest $request, Category $category): JsonResponse
    {
        $validated = $request->validated();
        $category->update($validated);

        return $this->success([
            'category' => new CategoryResource($category),
        ], 'Category updated successfully!');
    }

    public function destroy(Category $category): JsonResponse
    {
        $category->update(['status' => false]);

        return $this->success(null, 'Category deleted successfully!');
    }
}
