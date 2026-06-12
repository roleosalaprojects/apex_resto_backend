<?php

namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Admin\HelperController;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExpenseCategory\StoreRequest;
use App\Http\Requests\ExpenseCategory\UpdateRequest;
use App\Models\Accounting\ExpenseCategory;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Yajra\DataTables\Exceptions\Exception;

class ExpenseCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('admin.accounting.expense_categories.index');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['created_by'] = auth()->id();

        $category = ExpenseCategory::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Expense category "'.$category->name.'" created successfully.',
            'category' => $category,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(ExpenseCategory $expenseCategory): JsonResponse
    {
        $expenseCategory->load('createdBy');
        $expenseCategory->loadCount('expenses');

        return response()->json([
            'success' => true,
            'category' => $expenseCategory,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRequest $request, ExpenseCategory $expenseCategory): JsonResponse
    {
        $validated = $request->validated();
        $expenseCategory->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Expense category "'.$expenseCategory->name.'" updated successfully.',
            'category' => $expenseCategory,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ExpenseCategory $expenseCategory): JsonResponse
    {
        // Check if there are expenses using this category
        if ($expenseCategory->expenses()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete category with existing expenses. Please reassign or delete the expenses first.',
            ], 400);
        }

        $name = $expenseCategory->name;
        $expenseCategory->delete();

        return response()->json([
            'success' => true,
            'message' => 'Expense category "'.$name.'" deleted successfully.',
        ]);
    }

    /**
     * Get category for editing.
     */
    public function getCategory(ExpenseCategory $expenseCategory): JsonResponse
    {
        return response()->json([
            'success' => true,
            'category' => $expenseCategory,
        ]);
    }

    /**
     * Get all active categories for dropdowns.
     */
    public function getAll(): JsonResponse
    {
        $categories = ExpenseCategory::active()->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'categories' => $categories,
        ]);
    }

    /**
     * DataTable source.
     */
    public function table(): JsonResponse
    {
        $helper = new HelperController;
        $query = ExpenseCategory::withCount('expenses')->orderBy('name');

        try {
            return DataTables($query)
                ->addColumn('status_badge', function ($category) {
                    $color = $category->status ? 'success' : 'danger';
                    $text = $category->status ? 'Active' : 'Inactive';

                    return '<span class="badge bg-'.$color.'">'.$text.'</span>';
                })
                ->addColumn('actions', function ($category) use ($helper) {
                    return $helper->actionButtonsReturnModal($category, 'expense_categories', 'expense_category');
                })
                ->rawColumns(['status_badge', 'actions'])
                ->make(true);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function export(): StreamedResponse
    {
        $query = ExpenseCategory::withCount('expenses')->orderBy('name');

        $filename = 'expense_categories_'.now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Name', 'Description', 'Expenses Count', 'Status']);

            foreach ($query->lazy() as $category) {
                fputcsv($handle, [
                    $category->name,
                    $category->description,
                    $category->expenses_count,
                    $category->status ? 'Active' : 'Inactive',
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
