<?php

namespace App\Http\Controllers\API\v1\pos;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Accounting\PosLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosLogController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    public function store(Request $request): JsonResponse
    {
        PosLog::create([
            'cash_in' => $request->cash_in,
            'cash_out' => null,
            'rendered' => null,
            'type' => 4,
            'reason' => $request->reason.': '.$request->cash_in,
            'so_id' => null,
            'pos_id' => $request->pos_id,
            'store_id' => $request->store_id,
            'user_id' => $request->user_id,
        ]);

        return $this->success(null, 'Cash in of '.$request->cash_in.' has been created and logged.');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\PosLog  $posLog
     * @return \Illuminate\Http\Response
     */
    public function show(PosLog $posLog)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\PosLog  $posLog
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, PosLog $posLog)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\PosLog  $posLog
     * @return \Illuminate\Http\Response
     */
    public function destroy(PosLog $posLog)
    {
        //
    }

    /**
     * Record a cash-out (type 12).
     */
    public function cashOut(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:255',
            'pos_id' => 'required|numeric',
            'store_id' => 'required|numeric',
            'user_id' => 'required|numeric',
        ]);

        $posLog = PosLog::create([
            'cash_in' => null,
            'cash_out' => $request->amount,
            'rendered' => null,
            'type' => 12,
            'reason' => 'CASH-OUT: '.$request->reason,
            'so_id' => null,
            'pos_id' => $request->pos_id,
            'store_id' => $request->store_id,
            'user_id' => $request->user_id,
        ]);

        return $this->success($posLog, 'Cash out of '.$request->amount.' has been recorded.');
    }

    /**
     * Void a cash-out record (type 13). Creates a reversal record.
     */
    public function voidCashOut(Request $request, PosLog $posLog): JsonResponse
    {
        if ($posLog->type != 12) {
            return $this->error('This record is not a cash-out entry.', 422);
        }

        // Check if already voided
        $alreadyVoided = PosLog::where('type', 13)
            ->where('so_id', $posLog->id)
            ->exists();

        if ($alreadyVoided) {
            return $this->error('This cash-out has already been voided.', 422);
        }

        $voidLog = PosLog::create([
            'cash_in' => null,
            'cash_out' => $posLog->cash_out,
            'rendered' => null,
            'type' => 13,
            'reason' => 'VOID CASH-OUT: '.$posLog->reason,
            'so_id' => $posLog->id,
            'pos_id' => $posLog->pos_id,
            'store_id' => $posLog->store_id,
            'user_id' => $request->user_id ?? \Auth::user()->id,
        ]);

        return $this->success($voidLog, 'Cash out has been voided.');
    }

    /**
     * Get all cash-out records for the current session (unlinked to z-reading).
     */
    public function getCashOuts(Request $request): JsonResponse
    {
        $cashOuts = PosLog::where('pos_id', $request->pos_id)
            ->where('store_id', $request->store_id)
            ->where('type', 12)
            ->where('so_id', null)
            ->whereNull('shift_reading_id')
            ->get();

        $voidedIds = PosLog::where('pos_id', $request->pos_id)
            ->where('store_id', $request->store_id)
            ->where('type', 13)
            ->whereNotNull('so_id')
            ->whereNull('shift_reading_id')
            ->pluck('so_id')
            ->toArray();

        return $this->success([
            'cash_outs' => $cashOuts,
            'voided_cash_out_ids' => $voidedIds,
        ]);
    }
}
