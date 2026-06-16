<?php

namespace App\Services;

use App\Models\Pos\Sale;
use Carbon\Carbon;

/**
 * BIR statutory report aggregations (RMO 24-2023 / Annex F family).
 *
 * Every query is tenant-scoped and excludes training-mode transactions so
 * the figures match the official, fiscally-reported series. Mirrors the
 * shape conventions of ReportService.
 */
class BirReportService
{
    /**
     * Per-day BIR sales summary: beginning/ending SI, gross, less
     * discounts/returns/voids/VAT-adjustments, net, and VAT breakdown.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getBirSalesSummary(int $userId, string $start, string $end, ?int $storeId = null): array
    {
        [$from, $to] = $this->range($start, $end);

        $rows = Sale::query()
            ->where('user_id', $userId)
            ->where('is_training', false)
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('DATE(created_at) as day')
            ->selectRaw('MIN(CASE WHEN type = 0 AND cancelled = 0 THEN son END) as beginning_si')
            ->selectRaw('MAX(CASE WHEN type = 0 AND cancelled = 0 THEN son END) as ending_si')
            ->selectRaw('COALESCE(SUM(CASE WHEN type = 0 AND cancelled = 0 THEN total ELSE 0 END), 0) as gross_sales')
            ->selectRaw('COALESCE(SUM(CASE WHEN type = 0 AND cancelled = 0 THEN discount + sc_discount + pwd_discount + sp_discount + naac_discount ELSE 0 END), 0) as discounts')
            ->selectRaw('COALESCE(SUM(CASE WHEN type = 1 THEN total ELSE 0 END), 0) as returns')
            ->selectRaw('COALESCE(SUM(CASE WHEN type = 0 AND cancelled = 1 THEN total ELSE 0 END), 0) as voids')
            ->selectRaw('COALESCE(SUM(CASE WHEN type = 0 AND cancelled = 0 THEN vat_special_discounts ELSE 0 END), 0) as vat_adjustments')
            ->selectRaw('COALESCE(SUM(CASE WHEN type = 0 AND cancelled = 0 THEN vatable ELSE 0 END), 0) as vatable')
            ->selectRaw('COALESCE(SUM(CASE WHEN type = 0 AND cancelled = 0 THEN vat ELSE 0 END), 0) as vat')
            ->selectRaw('COALESCE(SUM(CASE WHEN type = 0 AND cancelled = 0 THEN vat_exempt ELSE 0 END), 0) as vat_exempt')
            ->selectRaw('COALESCE(SUM(CASE WHEN type = 0 AND cancelled = 0 THEN zero_rated ELSE 0 END), 0) as zero_rated')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        return $rows->map(function ($r) {
            $net = (float) $r->gross_sales - (float) $r->discounts - (float) $r->returns - (float) $r->vat_adjustments;

            return [
                'day' => $r->day,
                'beginning_si' => $r->beginning_si,
                'ending_si' => $r->ending_si,
                'gross_sales' => round((float) $r->gross_sales, 2),
                'discounts' => round((float) $r->discounts, 2),
                'returns' => round((float) $r->returns, 2),
                'voids' => round((float) $r->voids, 2),
                'vat_adjustments' => round((float) $r->vat_adjustments, 2),
                'net_sales' => round($net, 2),
                'vatable' => round((float) $r->vatable, 2),
                'vat' => round((float) $r->vat, 2),
                'vat_exempt' => round((float) $r->vat_exempt, 2),
                'zero_rated' => round((float) $r->zero_rated, 2),
            ];
        })->all();
    }

    /**
     * Discount sales book for a beneficiary class.
     *
     * @param  string  $type  sc|pwd|naac|solo_parent
     * @return array<int, array<string, mixed>>
     */
    public function getDiscountSalesBook(int $userId, string $type, string $start, string $end, ?int $storeId = null): array
    {
        [$from, $to] = $this->range($start, $end);

        $map = [
            'sc' => ['col' => 'sc_discount', 'special' => 1],
            'pwd' => ['col' => 'pwd_discount', 'special' => 2],
            'solo_parent' => ['col' => 'sp_discount', 'special' => 3],
            'naac' => ['col' => 'naac_discount', 'special' => 4],
        ];
        $config = $map[$type] ?? $map['sc'];
        $col = $config['col'];

        return Sale::query()
            ->where('user_id', $userId)
            ->where('is_training', false)
            ->where('type', false)
            ->where('cancelled', false)
            ->where($col, '>', 0)
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->whereBetween('created_at', [$from, $to])
            ->with('customer:id,name')
            ->orderBy('created_at')
            ->get()
            ->map(fn (Sale $s) => [
                'date' => $s->created_at->toDateString(),
                'si_no' => $s->son,
                'customer' => $s->special_discount_name ?: ($s->customer?->name ?? 'Walk-in'),
                'id_no' => $s->special_discount_id,
                'tin' => $s->special_discount_tin,
                'gross' => round((float) $s->total + (float) $s->$col, 2),
                'discount' => round((float) $s->$col, 2),
                'net' => round((float) $s->total, 2),
            ])->all();
    }

    /**
     * Voided transactions with their void document numbers.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getVoidedTransactions(int $userId, string $start, string $end, ?int $storeId = null): array
    {
        [$from, $to] = $this->range($start, $end);

        return Sale::query()
            ->where('user_id', $userId)
            ->where('is_training', false)
            ->where('cancelled', true)
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('void_no')
            ->get()
            ->map(fn (Sale $s) => [
                'void_no' => $s->void_no,
                'si_no' => $s->son,
                'date' => $s->created_at->toDateString(),
                'amount' => round((float) $s->total, 2),
                'approved_by' => $s->approved_by,
            ])->all();
    }

    /**
     * Adjustments = returns/refunds with their return document numbers.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAdjustments(int $userId, string $start, string $end, ?int $storeId = null): array
    {
        [$from, $to] = $this->range($start, $end);

        return Sale::query()
            ->where('user_id', $userId)
            ->where('is_training', false)
            ->where('type', true)
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('return_no')
            ->get()
            ->map(fn (Sale $s) => [
                'return_no' => $s->return_no,
                'si_no' => $s->son,
                'original_si_id' => $s->sale_id,
                'date' => $s->created_at->toDateString(),
                'amount' => round((float) $s->total, 2),
                'vat' => round((float) $s->vat, 2),
            ])->all();
    }

    /**
     * Daily sales grouped by VAT class.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getDailySalesByVatClass(int $userId, string $start, string $end, ?int $storeId = null): array
    {
        [$from, $to] = $this->range($start, $end);

        return Sale::query()
            ->where('user_id', $userId)
            ->where('is_training', false)
            ->where('type', false)
            ->where('cancelled', false)
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('DATE(created_at) as day')
            ->selectRaw('COALESCE(SUM(vatable), 0) as vatable')
            ->selectRaw('COALESCE(SUM(vat), 0) as vat')
            ->selectRaw('COALESCE(SUM(vat_exempt), 0) as vat_exempt')
            ->selectRaw('COALESCE(SUM(zero_rated), 0) as zero_rated')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn ($r) => [
                'day' => $r->day,
                'vatable' => round((float) $r->vatable, 2),
                'vat' => round((float) $r->vat, 2),
                'vat_exempt' => round((float) $r->vat_exempt, 2),
                'zero_rated' => round((float) $r->zero_rated, 2),
            ])->all();
    }

    /**
     * Stream a list of associative rows as CSV (mirrors
     * ReportService::generateSoldItemsCsv).
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function generateCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($rows !== []) {
            fputcsv($handle, array_keys($rows[0]));
            foreach ($rows as $row) {
                fputcsv($handle, array_values($row));
            }
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function range(string $start, string $end): array
    {
        $tz = config('app.timezone', 'Asia/Manila');

        return [
            Carbon::parse($start, $tz)->startOfDay(),
            Carbon::parse($end, $tz)->endOfDay(),
        ];
    }
}
