<?php

namespace App\Http\Controllers\API\v1\pos;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Restaurant\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ReservationController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $userId = Auth::guard('api')->user()->user_id;

        $reservations = Reservation::query()
            ->where('user_id', $userId)
            ->when($request->filled('from'), fn ($q) => $q->where('reserved_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('reserved_at', '<=', $request->date('to')))
            ->with('table:id,name,number')
            ->orderBy('reserved_at')
            ->get();

        return $this->success($reservations);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => ['nullable', 'integer'],
            'name' => ['required', 'string'],
            'phone' => ['nullable', 'string'],
            'party_size' => ['required', 'integer', 'min:1'],
            'reserved_at' => ['required', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
            'table_id' => ['nullable', 'integer', 'exists:restaurant_tables,id'],
            'notes' => ['nullable', 'string'],
            'store_id' => ['nullable', 'integer'],
        ]);

        $reservation = Reservation::create(array_merge($validated, [
            'status' => Reservation::STATUS_PENDING,
            'duration_minutes' => $validated['duration_minutes'] ?? 90,
            'user_id' => Auth::guard('api')->user()->user_id,
        ]));

        return $this->created($reservation);
    }

    public function updateStatus(Request $request, Reservation $reservation): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(Reservation::STATUSES)],
        ]);

        $reservation->update(['status' => $validated['status']]);

        return $this->success($reservation);
    }
}
