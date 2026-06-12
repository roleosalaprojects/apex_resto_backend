<?php

namespace App\Http\Controllers\Admin\Pos;

use App\Http\Controllers\Controller;
use App\Models\Employees\Role;
use App\Models\Pos\Voucher;
use App\Models\Settings\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VoucherController extends Controller
{
    public function index()
    {
        $access = Role::find(auth()->user()->role_id);

        return view('admin.pos.vouchers.index', compact('access'));
    }

    public function create()
    {
        $access = Role::find(auth()->user()->role_id);
        $voucher = new Voucher;
        $stores = Store::all();

        return view('admin.pos.vouchers.create', compact('access', 'voucher', 'stores'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'nullable|string|max:50|unique:vouchers,code',
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'minimum_amount' => 'nullable|numeric|min:0',
            'max_uses' => 'required|integer|min:1',
            'store_id' => 'nullable|exists:stores,id',
            'expires_at' => 'required|date|after:now',
            'is_active' => 'nullable|boolean',
        ]);

        // Generate code if not provided
        if (empty($validated['code'])) {
            $validated['code'] = $this->generateUniqueCode();
        } else {
            $validated['code'] = strtoupper($validated['code']);
        }

        $validated['minimum_amount'] = $validated['minimum_amount'] ?? 0;
        $validated['is_active'] = $request->has('is_active');

        Voucher::create($validated);

        return redirect()->route('vouchers.index')->with('success', 'Voucher created successfully!');
    }

    public function show(Voucher $voucher)
    {
        $access = Role::find(auth()->user()->role_id);

        return view('admin.pos.vouchers.show', compact('access', 'voucher'));
    }

    public function edit(Voucher $voucher)
    {
        $access = Role::find(auth()->user()->role_id);
        $stores = Store::all();

        return view('admin.pos.vouchers.edit', compact('access', 'voucher', 'stores'));
    }

    public function update(Request $request, Voucher $voucher)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:vouchers,code,'.$voucher->id,
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'minimum_amount' => 'nullable|numeric|min:0',
            'max_uses' => 'required|integer|min:1',
            'store_id' => 'nullable|exists:stores,id',
            'expires_at' => 'required|date',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['code'] = strtoupper($validated['code']);
        $validated['minimum_amount'] = $validated['minimum_amount'] ?? 0;
        $validated['is_active'] = $request->has('is_active');

        $voucher->update($validated);

        return redirect()->route('vouchers.index')->with('success', 'Voucher updated successfully!');
    }

    public function destroy(Voucher $voucher)
    {
        $voucher->delete();

        return redirect()->route('vouchers.index')->with('success', 'Voucher deleted successfully!');
    }

    public function table(Request $request)
    {
        $q = Voucher::query()
            ->select('id', 'code', 'name', 'amount', 'minimum_amount', 'max_uses', 'used_count', 'store_id', 'expires_at', 'is_active')
            ->with('store:id,name');

        return DataTables($q)
            ->addColumn('status', function (Voucher $voucher) {
                if (! $voucher->is_active) {
                    return '<span class="badge badge-secondary">Inactive</span>';
                }
                if ($voucher->isExpired()) {
                    return '<span class="badge badge-danger">Expired</span>';
                }
                if (! $voucher->hasUsesRemaining()) {
                    return '<span class="badge badge-warning">Used Up</span>';
                }

                return '<span class="badge badge-success">Active</span>';
            })
            ->addColumn('usage', function (Voucher $voucher) {
                return $voucher->used_count.' / '.$voucher->max_uses;
            })
            ->addColumn('store_name', function (Voucher $voucher) {
                return $voucher->store?->name ?? 'All Stores';
            })
            ->addColumn('actions', function (Voucher $voucher) {
                $action = "<div class='d-flex justify-content-end flex-shrink-0'>";
                // View Button
                $action .= '<a href="'.route('vouchers.show', $voucher->id).'" class="btn btn-icon btn-bg-light btn-active-color-primary btn-sm me-1" data-bs-toggle="tooltip" title="View"><i class="fas fa-eye"></i></a>';
                // Edit Button
                $action .= '<a href="'.route('vouchers.edit', $voucher->id).'" class="btn btn-icon btn-bg-light btn-active-color-info btn-sm me-1" data-bs-toggle="tooltip" title="Edit"><i class="fas fa-edit"></i></a>';
                // Delete Button
                $action .= '<span data-bs-toggle="modal" data-bs-target="#deleteModal"><button type="button" class="btn btn-icon btn-bg-light btn-active-color-danger btn-sm" value="'.$voucher->id.'" data-bs-toggle="tooltip" title="Delete"><i class="fas fa-trash"></i></button></span>';
                $action .= '<input type="hidden" id="name_'.$voucher->id.'" value="'.$voucher->code.'" />';
                $action .= '<form method="POST" action="'.route('vouchers.destroy', $voucher->id).'" id="form_delete_'.$voucher->id.'">'.method_field('DELETE').csrf_field().'</form>';
                $action .= '</div>';

                return $action;
            })
            ->rawColumns(['status', 'actions'])
            ->make(true);
    }

    public function generateCode()
    {
        return response()->json([
            'code' => $this->generateUniqueCode(),
        ]);
    }

    private function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (Voucher::where('code', $code)->exists());

        return $code;
    }
}
