<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Jobs\Admin\NormalizeReceiptJob;
use App\Jobs\Admin\SaleAdjustmentJob;
use App\Models\Pos\Sale;
use App\Models\Pos\Xreading;
use App\Models\Pos\Zreading;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

class AdjustmentController extends Controller
{
    public function getReceipts(Request $request)
    {
        $start = Carbon::parse($request->startDate)->startOfDay()->toDateTimeString();
        $end = Carbon::parse($request->endDate)->endOfDay()->toDateTimeString();
        $query = Sale::query()->whereBetween('created_at', [$start, $end]);

        return DataTables($query)->make('true');
    }

    // v2
    public function adjustReceipts(Request $request)
    {
        $request->validate([
            'startDate' => 'required',
            'endDate' => 'required',
            'selectedPos' => ['required', 'array'],
            'vr' => ['required', 'numeric', 'min:-100', 'max:100'],
            'nvr' => ['required', 'numeric', 'min:-100', 'max:100'],
            'zrr' => ['required', 'numeric', 'min:-100', 'max:100'],
        ]);

        Bus::chain([
            new NormalizeReceiptJob(
                $request->startDate,
                $request->endDate,
            ),
            new SaleAdjustmentJob(
                $request->startDate,
                $request->endDate,
                $request->selectedPos,
                $request->vr,
                $request->nvr,
                $request->zrr,
            ),
        ])
            ->catch(function (\Throwable $e) {
                \Log::log('Job Error', $e);
            })
            ->dispatch();

        return response()->json("Records From $request->startDate to $request->endDate Has been queued for adjustment!");
    }

    public function normalizeReceipts(Request $request)
    {
        $startDate = Carbon::parse($request->startDate)->startOfDay()->toDateTimeString();
        $endDate = Carbon::parse($request->endDate)->endOfDay()->toDateTimeString();
        NormalizeReceiptJob::dispatch($startDate, $endDate);

        return response()->json("Records From $startDate to $endDate Has been Successfully Queued For Receipt Normalization!");
    }

    public function readings(Request $request)
    {
        $start = Carbon::parse($request->startDate)->startOfDay()->toDateTimeString();
        $end = Carbon::parse($request->endDate)->endOfDay()->toDateTimeString();

        $x = DB::select(
            '
            SELECT
                x.id,
                "x" as type,
                p.number as terminal,
                format(x.cash, 2) as gross,
                format(x.refunds, 2) as refunds,
                format(x.cash - x.refunds, 2) as net,
                vatable,
                vat,
                non_vat + vat_exempt as non_vat,
                excess_vatable,
                excess_vat,
                excess_non_vat
            FROM
                xreadings x
            LEFT JOIN
                pos p
            ON
                p.id = x.pos_id
            LEFT JOIN
                stores s
            ON
                s.id = x.store_id
            LEFT JOIN
                users u
            ON
                u.id = x.user_id
            WHERE
            x.created_at
                BETWEEN
                "'.$start.'"
                AND
                "'.$end.'"
            ');
        $z = DB::select(
            '
            SELECT
                z.id,
                "z" as type,
                p.number as terminal,
                format(z.cash, 2) as gross,
                format(z.refund, 2) as refunds,
                format(z.cash - z.refund, 2) as net,
                vatable,
                vat,
                non_vat + vat_exempt as non_vat,
                excess_vatable,
                excess_vat,
                excess_non_vat
            FROM
                zreadings z
            LEFT JOIN
                pos p
            ON
                p.id = z.pos_id
            LEFT JOIN
                stores s
            ON
                s.id = z.store_id
            LEFT JOIN
                users u
            ON
                u.id = z.user_id
            WHERE
            z.created_at
                BETWEEN
                "'.$start.'"
                AND
                "'.$end.'"
            '
        );
        $data = array_merge($x, $z);

        return datatables($data)
            ->make(true);
    }

    public function adjustReadings(Request $request)
    {
        $request->validate([
            'startDate' => 'required',
            'endDate' => 'required',
            'vr' => ['required', 'numeric', 'min:-100', 'max:100'],
            'nvr' => ['required', 'numeric', 'min:-100', 'max:100'],
            'zrr' => ['required', 'numeric', 'min:-100', 'max:100'],
        ]);

        // Normalize The Reading First
        $this->normalizeReadings($request);

        $start = Carbon::parse($request->startDate)->startOfDay()->toDateTimeString();
        $end = Carbon::parse($request->endDate)->endOfDay()->toDateTimeString();
        $vr = $request->vr / 100;
        $nvr = $request->nvr / 100;

        $xreadings = Xreading::whereBetween('created_at', [$start, $end])->get();
        $zreadings = Zreading::whereBetween('created_at', [$start, $end])->get();
        foreach ($xreadings as $reading) {
            // Non VAT
            $reading->excess_non_vat = ($reading->non_vat + $reading->vat_exempt) * $nvr;
            // VAT
            $reading->excess_vatable = $reading->vatable * $vr;
            $reading->excess_vat = ($reading->vatable * 0.12) * $vr;
            $reading->save();
        }
        foreach ($zreadings as $reading) {
            // Non VAT
            $reading->excess_non_vat = ($reading->non_vat + $reading->vat_exempt) * $nvr;
            // VAT
            $reading->excess_vatable = $reading->vatable * $vr;
            $reading->excess_vat = ($reading->vatable * 0.12) * $vr;
            $reading->save();
        }

        return response()->json("Records From $start to $end Has been Successfully Adjusted!");
    }

    public function normalizeReadings(Request $request)
    {
        $request->validate([
            'startDate' => 'required',
            'endDate' => 'required',
            'vr' => ['required', 'numeric', 'min:-100', 'max:100'],
            'nvr' => ['required', 'numeric', 'min:-100', 'max:100'],
            'zrr' => ['required', 'numeric', 'min:-100', 'max:100'],
        ]);
        $start = Carbon::parse($request->startDate)->startOfDay()->toDateTimeString();
        $end = Carbon::parse($request->endDate)->endOfDay()->toDateTimeString();
        $vr = $request->vr / 100;
        $nvr = $request->nvr / 100;

        $xreadings = Xreading::whereBetween('created_at', [$start, $end])->get();
        $zreadings = Zreading::whereBetween('created_at', [$start, $end])->get();

        foreach ($zreadings as $zreading) {
            $VATSum = $zreading->net_sales - $zreading->non_vat;
            $VATable = $VATSum / 1.12;
            $VAT = $VATable * 0.12;
            $zreading->update([
                'vatable' => $VATable,
                'vat' => $VAT,
            ]);
        }
        foreach ($xreadings as $xreading) {
            $VATSum = $xreading->vatable + $xreading->vat;
            $VATable = $VATSum / 1.12;
            $VAT = $VATable * 0.12;
            $xreading->update([
                'vatable' => $VATable,
                'vat' => $VAT,
            ]);
        }
    }
}
