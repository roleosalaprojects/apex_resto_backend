<?php

namespace App\Http\Controllers\API\v1\pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Category\StoreRequest;
use App\Http\Requests\Category\UpdateRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Traits\ApiResponse;
use App\Models\Products\Category;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $categories = Category::where('status', true)->get();

        return $this->success(CategoryResource::collection($categories));
    }

    public function store(StoreRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $category = Category::create([
            'name' => $validated['name'],
            'status' => true,
            'user_id' => auth()->user()->user_id,
        ]);

        return $this->created(
            new CategoryResource($category),
            $category->name.' category added!'
        );
    }

    public function show(Category $category): JsonResponse
    {
        $category->load('items');

        return $this->success(new CategoryResource($category));
    }

    public function update(UpdateRequest $request, Category $category): JsonResponse
    {
        $validated = $request->validated();

        $category->update([
            'name' => $validated['name'],
        ]);

        return $this->success(
            new CategoryResource($category),
            $category->name.' category updated!'
        );
    }

    public function destroy(Category $category): JsonResponse
    {
        if (! $category->status) {
            return $this->forbidden($category->name.' has already been deleted.');
        }

        $category->update([
            'status' => false,
        ]);

        return $this->success(null, $category->name.' category deleted!');
    }
}
