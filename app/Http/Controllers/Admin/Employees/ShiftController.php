<?php

namespace App\Http\Controllers\Admin\Employees;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Shift\ClockInRequest;
use App\Http\Requests\Admin\Shift\ClockOutRequest;
use App\Models\Employees\Role;
use App\Models\Employees\Shift;
use App\Models\Employees\ShiftBreak;
use App\Models\Settings\Pos;
use App\Models\Settings\Store;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function index(): View
    {
        $shifts = Shift::query()
            ->where('user_id', auth()->user()->user_id)
            ->with(['user', 'pos', 'store', 'breaks'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $access = Role::find(auth()->user()->role_id);

        return view('admin.employees.shifts.index', compact('shifts', 'access'));
    }

    public function create(): View
    {
        $stores = Store::where('user_id', auth()->user()->user_id)->get();
        $terminals = Pos::where('user_id', auth()->user()->user_id)->get();
        $activeShift = $this->getActiveShift();
        $access = Role::find(auth()->user()->role_id);

        return view('admin.employees.shifts.create', compact('stores', 'terminals', 'activeShift', 'access'));
    }

    public function store(ClockInRequest $request): RedirectResponse
    {
        $activeShift = $this->getActiveShift();

        if ($activeShift) {
            return redirect()->route('shifts.index')
                ->with('error', 'You already have an active shift. Please clock out first.');
        }

        Shift::create([
            'user_id' => auth()->id(),
            'pos_id' => $request->pos_id,
            'store_id' => $request->store_id,
            'clock_in' => now(),
            'starting_cash' => $request->starting_cash,
            'notes' => $request->notes,
            'status' => 'active',
        ]);

        return redirect()->route('shifts.index')
            ->with('msg', 'Successfully clocked in!');
    }

    public function show(Shift $shift): View
    {
        $shift->load(['user', 'pos', 'store', 'breaks']);
        $access = Role::find(auth()->user()->role_id);

        return view('admin.employees.shifts.show', compact('shift', 'access'));
    }

    public function edit(Shift $shift): View
    {
        $access = Role::find(auth()->user()->role_id);

        return view('admin.employees.shifts.edit', compact('shift', 'access'));
    }

    public function update(Request $request, Shift $shift): RedirectResponse
    {
        $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $shift->update([
            'notes' => $request->notes,
        ]);

        return redirect()->route('shifts.show', $shift)
            ->with('msg', 'Shift notes updated successfully.');
    }

    public function destroy(Shift $shift): RedirectResponse
    {
        if ($shift->status === 'active') {
            $shift->update(['status' => 'cancelled']);
        }

        return redirect()->route('shifts.index')
            ->with('msg', 'Shift cancelled successfully.');
    }

    public function clockOut(ClockOutRequest $request, Shift $shift): RedirectResponse
    {
        if ($shift->status !== 'active') {
            return redirect()->route('shifts.index')
                ->with('error', 'This shift is not active.');
        }

        $activeBreak = $shift->breaks()->whereNull('break_end')->first();
        if ($activeBreak) {
            $activeBreak->update(['break_end' => now()]);
        }

        $shift->update([
            'clock_out' => now(),
            'ending_cash' => $request->ending_cash,
            'expected_cash' => $shift->starting_cash,
            'cash_difference' => $request->ending_cash - $shift->starting_cash,
            'notes' => $request->notes ?? $shift->notes,
            'status' => 'completed',
        ]);

        return redirect()->route('shifts.index')
            ->with('msg', 'Successfully clocked out!');
    }

    public function startBreak(Request $request, Shift $shift): RedirectResponse
    {
        $request->validate([
            'type' => ['required', 'in:lunch,short,other'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        if ($shift->status !== 'active') {
            return redirect()->route('shifts.show', $shift)
                ->with('error', 'Cannot start break on inactive shift.');
        }

        $activeBreak = $shift->breaks()->whereNull('break_end')->first();
        if ($activeBreak) {
            return redirect()->route('shifts.show', $shift)
                ->with('error', 'You already have an active break.');
        }

        ShiftBreak::create([
            'shift_id' => $shift->id,
            'break_start' => now(),
            'type' => $request->type,
            'reason' => $request->reason,
        ]);

        return redirect()->route('shifts.show', $shift)
            ->with('msg', 'Break started.');
    }

    public function endBreak(Shift $shift): RedirectResponse
    {
        $activeBreak = $shift->breaks()->whereNull('break_end')->first();

        if (! $activeBreak) {
            return redirect()->route('shifts.show', $shift)
                ->with('error', 'No active break found.');
        }

        $activeBreak->update(['break_end' => now()]);

        return redirect()->route('shifts.show', $shift)
            ->with('msg', 'Break ended.');
    }

    public function table(Request $request): View
    {
        $query = Shift::query()
            ->where('user_id', auth()->user()->user_id)
            ->with(['user', 'pos', 'store']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('clock_in', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('clock_in', '<=', $request->date_to);
        }

        $shifts = $query->orderBy('created_at', 'desc')->paginate(15);

        return view('admin.employees.shifts.table', compact('shifts'));
    }

    private function getActiveShift(): ?Shift
    {
        return Shift::where('user_id', auth()->id())
            ->where('status', 'active')
            ->first();
    }
}
