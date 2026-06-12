<?php

namespace App\Http\Controllers\API\v1\openclaw;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\v1\openclaw\ExpenseCategory\StoreRequest;
use App\Http\Requests\API\v1\openclaw\ExpenseCategory\UpdateRequest;
use App\Models\Accounting\ExpenseCategory;
use Illuminate\Http\JsonResponse;

/**
 * OpenClaw bot write surface for expense categories.
 *
 * Gated by the `openclaw:expense-categories:write` ability. Read is
 * still served by ExpenseController::categories() under the broader
 * `openclaw:read` ability.
 *
 * Categories are shared platform-wide today (the table has no
 * `user_id` column, so uniqueness is global). The case-insensitive
 * comparison comes from MySQL's default `utf8mb4_unicode_ci` collation
 * on the `name` column — calling `whereRaw('LOWER(name) = ?')` would be
 * redundant. See development/recommendations/multi_tenancy_enforcement.md
 * for the larger discussion of when this might become per-tenant.
 */
class ExpenseCategoryController extends Controller
{
    public function store(StoreRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $existing = ExpenseCategory::query()
            ->where('name', $validated['name'])
            ->first();

        if ($existing !== null) {
            return response()->json([
                'success' => false,
                'message' => 'A category with that name already exists.',
                'data' => [
                    'category' => [
                        'id' => $existing->id,
                        'name' => $existing->name,
                        'description' => $existing->description,
                        'status' => (bool) $existing->status,
                    ],
                ],
            ], 409);
        }

        $category = ExpenseCategory::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'status' => true,
            'created_by' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Expense category created.',
            'data' => [
                'category' => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'description' => $category->description,
                    'status' => (bool) $category->status,
                ],
            ],
        ], 201);
    }

    public function update(UpdateRequest $request, ExpenseCategory $expenseCategory): JsonResponse
    {
        $validated = $request->validated();

        if (array_key_exists('name', $validated) && $validated['name'] !== $expenseCategory->name) {
            $conflict = ExpenseCategory::query()
                ->where('name', $validated['name'])
                ->where('id', '!=', $expenseCategory->id)
                ->first();

            if ($conflict !== null) {
                return response()->json([
                    'success' => false,
                    'message' => 'A different category with that name already exists.',
                    'data' => [
                        'category' => [
                            'id' => $conflict->id,
                            'name' => $conflict->name,
                            'description' => $conflict->description,
                            'status' => (bool) $conflict->status,
                        ],
                    ],
                ], 409);
            }
        }

        $expenseCategory->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Expense category updated.',
            'data' => [
                'category' => [
                    'id' => $expenseCategory->id,
                    'name' => $expenseCategory->name,
                    'description' => $expenseCategory->description,
                    'status' => (bool) $expenseCategory->status,
                ],
            ],
        ]);
    }
}
