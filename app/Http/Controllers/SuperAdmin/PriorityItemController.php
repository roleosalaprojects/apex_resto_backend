<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Products\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

/**
 * Manage the items.priority flag — a curated list of items the owner
 * wants to watch closely. The flag is used by the upcoming admin
 * dashboard widget + live-sales-count surface.
 *
 * Scope is intentionally narrow:
 *   - List items where priority = true
 *   - Bulk-add items (set priority = true)
 *   - Remove an item (set priority = false)
 *
 * The flag does NOT participate in BIR reporting, e-journal output,
 * zreadings aggregation, or the existing /superadmin/adjustment flow.
 * This controller only mutates the priority column on items.
 */
class PriorityItemController extends Controller
{
    /**
     * GET /superadmin/priority-items — landing page.
     */
    public function index(): View
    {
        return view('superadmin.priority-items.index');
    }

    /**
     * GET /superadmin/priority-items/data — DataTables JSON for the
     * "currently prioritized" table.
     */
    public function data(Request $request): JsonResponse
    {
        $query = Item::query()
            ->where('priority', true)
            ->with(['category:id,name', 'supplier:id,name'])
            ->select(['id', 'name', 'barcode', 'category_id', 'supplier_id', 'cost', 'price', 'status']);

        return DataTables::of($query)
            ->addColumn('category_name', fn (Item $item) => $item->category?->name ?? '—')
            ->addColumn('supplier_name', fn (Item $item) => $item->supplier?->name ?? '—')
            ->editColumn('cost', fn (Item $item) => number_format((float) $item->cost, 2))
            ->editColumn('price', fn (Item $item) => number_format((float) $item->price, 2))
            ->addColumn('status_badge', fn (Item $item) => $item->status
                ? '<span class="badge badge-success">Active</span>'
                : '<span class="badge badge-secondary">Inactive</span>')
            ->rawColumns(['status_badge'])
            ->make(true);
    }

    /**
     * GET /superadmin/priority-items/available — DataTables JSON for the
     * "add items" modal picker. Lists items where priority = false.
     */
    public function available(Request $request): JsonResponse
    {
        $query = Item::query()
            ->where('priority', false)
            ->where('status', true)
            ->with(['category:id,name'])
            ->select(['id', 'name', 'barcode', 'category_id', 'price']);

        return DataTables::of($query)
            ->addColumn('category_name', fn (Item $item) => $item->category?->name ?? '—')
            ->editColumn('price', fn (Item $item) => number_format((float) $item->price, 2))
            ->make(true);
    }

    /**
     * POST /superadmin/priority-items/add — bulk-flip selected items to
     * priority = true. Body: { "item_ids": [1, 2, 3, ...] }.
     */
    public function add(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['integer', 'exists:items,id'],
        ]);

        $affected = Item::query()
            ->whereIn('id', $validated['item_ids'])
            ->where('priority', false)
            ->update(['priority' => true]);

        return response()->json([
            'success' => true,
            'message' => "Added {$affected} item(s) to the priority list.",
            'count' => $affected,
        ]);
    }

    /**
     * POST /superadmin/priority-items/{item}/remove — flip a single item
     * back to priority = false.
     */
    public function remove(Item $item): JsonResponse
    {
        $item->update(['priority' => false]);

        return response()->json([
            'success' => true,
            'message' => "Removed '{$item->name}' from the priority list.",
        ]);
    }
}
