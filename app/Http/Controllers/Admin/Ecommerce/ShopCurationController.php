<?php

namespace App\Http\Controllers\Admin\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\Products\Category;
use App\Models\Products\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class ShopCurationController extends Controller
{
    private const MAX_DISPLAYED = 12;

    public function index(): View
    {
        return view('admin.ecommerce.curation.index', [
            'maxDisplayed' => self::MAX_DISPLAYED,
        ]);
    }

    // -----------------------------------------------------------------
    // Categories
    // -----------------------------------------------------------------

    public function categoriesFeatured(): JsonResponse
    {
        $rows = Category::featuredSpotlight()
            ->limit(50)
            ->get(['id', 'name', 'icon', 'featured_order']);

        return response()->json(['data' => $rows]);
    }

    public function categoriesSearch(Request $request): JsonResponse
    {
        $query = Category::query()
            ->where('status', true)
            ->where('featured', false)
            ->select(['id', 'name', 'icon']);

        return DataTables::of($query)->toJson();
    }

    public function featureCategory(Category $category): JsonResponse
    {
        $nextOrder = (Category::where('featured', true)->max('featured_order') ?? 0) + 10;
        $category->update([
            'featured' => true,
            'featured_order' => $nextOrder,
        ]);

        return response()->json(['ok' => true]);
    }

    public function unfeatureCategory(Category $category): JsonResponse
    {
        $category->update([
            'featured' => false,
            'featured_order' => null,
        ]);

        return response()->json(['ok' => true]);
    }

    public function reorderCategories(Request $request): JsonResponse
    {
        $ids = $this->validatedOrderedIds($request);
        $this->rewriteOrder(Category::class, $ids);

        return response()->json(['ok' => true]);
    }

    // -----------------------------------------------------------------
    // Items
    // -----------------------------------------------------------------

    public function itemsFeatured(): JsonResponse
    {
        $rows = Item::featuredSpotlight()
            ->limit(50)
            ->get(['id', 'name', 'price', 'image', 'featured_order']);

        return response()->json(['data' => $rows]);
    }

    public function itemsSearch(Request $request): JsonResponse
    {
        $query = Item::query()
            ->where('status', true)
            ->where('featured', false)
            ->select(['id', 'name', 'price', 'cost', 'markup', 'image']);

        return DataTables::of($query)->toJson();
    }

    public function featureItem(Item $item): JsonResponse
    {
        $nextOrder = (Item::where('featured', true)->max('featured_order') ?? 0) + 10;
        $item->update([
            'featured' => true,
            'featured_order' => $nextOrder,
        ]);

        return response()->json(['ok' => true]);
    }

    public function unfeatureItem(Item $item): JsonResponse
    {
        $item->update([
            'featured' => false,
            'featured_order' => null,
        ]);

        return response()->json(['ok' => true]);
    }

    public function reorderItems(Request $request): JsonResponse
    {
        $ids = $this->validatedOrderedIds($request);
        $this->rewriteOrder(Item::class, $ids);

        return response()->json(['ok' => true]);
    }

    // -----------------------------------------------------------------
    // Shared
    // -----------------------------------------------------------------

    /**
     * @return array<int, int>
     */
    private function validatedOrderedIds(Request $request): array
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer', 'min:1'],
        ]);

        return array_values(array_unique(array_map('intval', $validated['ids'])));
    }

    /**
     * Rewrite featured_order for the given model in one transaction.
     * Each ID gets featured_order = position * 10 (gaps for future inserts).
     * IDs not currently marked featured are silently ignored.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     * @param  array<int, int>  $orderedIds
     */
    private function rewriteOrder(string $modelClass, array $orderedIds): void
    {
        DB::transaction(function () use ($modelClass, $orderedIds) {
            foreach ($orderedIds as $position => $id) {
                $modelClass::where('id', $id)
                    ->where('featured', true)
                    ->update(['featured_order' => ($position + 1) * 10]);
            }
        });
    }
}
