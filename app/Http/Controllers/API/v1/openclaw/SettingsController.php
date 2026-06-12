<?php

namespace App\Http\Controllers\API\v1\openclaw;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\BusinessSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read + partial-update the singleton business_settings row.
 *
 * GET responses always include the full default shape so the bot/UI
 * never sees NULL where a number is expected. PATCH is deep-merge,
 * not replace: sending {"thresholds": {"low_stock_qty": 5}} updates
 * only that key and leaves the other thresholds intact.
 */
class SettingsController extends Controller
{
    use ApiResponse;

    public function show(Request $request): JsonResponse
    {
        $settings = BusinessSettings::current();

        return $this->success([
            'settings' => $settings->withDefaults(),
            'updated_at' => $settings->updated_at?->toIso8601String(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'thresholds' => 'sometimes|array',
            'thresholds.daily_sales_floor' => 'sometimes|nullable|numeric|min:0',
            'thresholds.daily_sales_check_after' => 'sometimes|nullable|date_format:H:i',

            'expense_rules' => 'sometimes|array',
            'expense_rules.default_expense_bank_id' => 'sometimes|nullable|integer|exists:banks,id',
            'expense_rules.receipt_required_above' => 'sometimes|nullable|numeric|min:0',
            'expense_rules.preferred_categories' => 'sometimes|nullable|array',
            'expense_rules.preferred_categories.*' => 'integer|exists:expense_categories,id',

            'supplier_rules' => 'sometimes|array',
            'supplier_rules.default_supplier_id' => 'sometimes|nullable|integer|exists:suppliers,id',
            'supplier_rules.treat_supplier_payments_as_expenses' => 'sometimes|boolean',
        ]);

        $settings = BusinessSettings::current();

        // Deep-merge each section so partial updates don't blow away siblings.
        if (array_key_exists('thresholds', $validated)) {
            $settings->thresholds = array_replace(
                $settings->thresholds ?? [],
                $validated['thresholds'],
            );
        }

        if (array_key_exists('expense_rules', $validated)) {
            $settings->expense_rules = array_replace(
                $settings->expense_rules ?? [],
                $validated['expense_rules'],
            );
        }

        if (array_key_exists('supplier_rules', $validated)) {
            $settings->supplier_rules = array_replace(
                $settings->supplier_rules ?? [],
                $validated['supplier_rules'],
            );
        }

        $settings->save();

        return $this->success([
            'settings' => $settings->withDefaults(),
            'updated_at' => $settings->updated_at?->toIso8601String(),
        ], 'Settings updated.');
    }
}
