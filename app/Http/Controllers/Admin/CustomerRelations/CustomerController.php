<?php

namespace App\Http\Controllers\Admin\CustomerRelations;

use App\Http\Controllers\Controller;
use App\Jobs\User\NewCustomerJob;
use App\Models\CustomerRelations\Customer;
use App\Models\Pos\Sale;
use Carbon\Carbon;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return RedirectResponse|Redirector|View
     */
    public function index(Request $request)
    {
        if (auth()->user()->role->cstmr) {
            $key = $request->table_search;
            $customers = Customer::where('user_id', auth()->user()->user_id)
                ->where(function ($query) use ($key) {
                    $query->orWhere('name', 'like', '%'.$key.'%');
                    $query->orWhere('code', 'like', '%'.$key.'%');
                    $query->orWhere('phone', 'like', '%'.$key.'%');
                })
                ->orderBy('name')
                ->paginate(10);

            return view('admin.customer-relations.customers.index', compact('customers'));
        }

        return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Factory|\Illuminate\Contracts\View\View|RedirectResponse|Redirector|View
     */
    public function create()
    {
        if (auth()->user()->role->cstmr_create) {
            $customer = new Customer;

            return view('admin.customer-relations.customers.create', compact('customer'));
        }

        return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return RedirectResponse
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'code' => ['required', Rule::unique('customers')],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'phone' => 'required',
            //            'email' => ['required', 'email',],
            //            'address'=>'required',
            //            'password' => ['required', 'confirmed']
            // 'city'=>'required',
            // 'zip'=>'required',
            // 'province'=>'required',
            // 'country'=>'required',
        ]);
        $code = Customer::where('code', strtoupper($request->code))->where('user_id', auth()->user()->user_id)->get();
        // dd($code);
        if (count($code) > 0) {
            return redirect()->back()->withInput()->withErrors(['error-msg' => 'Code is already taken. Please enter another code.']);
        }

        $defaults = DB::table('receipts')->first();

        $last_image = '';
        $brand_image = $request->file('image');
        if ($brand_image) {
            $last_image = $this->storeCustomerImage($brand_image);
        }

        $customer = Customer::create([
            'name' => ucwords($request->name),
            'code' => $request->code,
            'phone' => strtoupper($request->phone),
            'address' => strtoupper($request->address),
            'email' => $request->email,
            'city' => strtoupper($request->city),
            'zip' => strtoupper($request->zip),
            'province' => strtoupper($request->province),
            'country' => $request->country,
            'note' => $request->note,
            'points' => number_format($defaults->points, 5),
            'status' => true,
            'user_id' => auth()->user()->user_id,
            'tin' => $request->tin,
            'business_type' => strtoupper($request->business_type),
            'image' => $last_image,
            'e_name' => strtoupper($request->e_name),
            'e_phone' => strtoupper($request->e_phone),
            'e_address' => strtoupper($request->address),
        ]);
        \Bus::chain([
            new NewCustomerJob($customer),
        ])->dispatch();

        return redirect()->route('customers.index')->with('success', 'Customer successfully added!');
    }

    /**
     * Display the specified resource.
     *
     * @return Factory|\Illuminate\Contracts\View\View|Redirector|RedirectResponse|View
     */
    public function show(Customer $customer)
    {
        if (auth()->user()->role->cstmr_read) {
            $purchases = Sale::where('customer_id', $customer->id)->get();

            return view('admin.customer-relations.customers.show', compact('purchases', 'customer'));
        }

        return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return Factory|\Illuminate\Contracts\View\View|Redirector|RedirectResponse|View
     */
    public function edit(Customer $customer)
    {
        if (auth()->user()->role->cstmr_update) {
            return view('admin.customer-relations.customers.edit', compact('customer'));
        }

        return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");
    }

    /**
     * Update the specified resource in storage.
     *
     * @return RedirectResponse
     */
    public function update(Request $request, Customer $customer)
    {
        //
        $request->validate([
            'name' => 'required',
            'code' => ['required', Rule::unique('customers')->ignore($customer->id)],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            // 'phone' => 'required',
            // 'address' => 'required',
            // 'city' => 'required',
            // 'zip' => 'required',
            // 'province' => 'required',
            // 'country' => 'required',
            'points' => 'required|numeric',
            'credit_limit' => 'nullable|numeric|min:0',
            'credit_term_days' => 'nullable|integer|min:1',
        ]);

        $code = Customer::where('code', $request->code)->where('user_id', auth()->user()->user_id)->get();

        if (! $code) {
            return redirect()->back()->withInput()->withErrors(['error-msg' => 'Code is already taken. Please enter another code.']);
        }

        $old_image = $request->old_image;

        $brand_image = $request->file('image');
        if ($brand_image) {
            $last_image = $this->storeCustomerImage($brand_image);
            if ($request->old_image) {
                if (file_exists($old_image)) {
                    unlink($old_image);
                }
            }
            Customer::where('id', $customer->id)->update([
                'image' => $last_image,
            ]);
        }

        $data = [
            'name' => strtoupper($request->name),
            'code' => $request->code,
            'phone' => strtoupper($request->phone),
            'address' => strtoupper($request->address),
            'email' => $request->email,
            'city' => strtoupper($request->city),
            'zip' => strtoupper($request->zip),
            'province' => strtoupper($request->province),
            'country' => $request->country,
            'note' => $request->note,
            'points' => number_format($request->points, 5),
            'status' => true,
            'user_id' => auth()->user()->user_id,
            'tin' => $request->tin,
            'business_type' => strtoupper($request->business_type),
            'e_name' => strtoupper($request->e_name),
            'e_phone' => strtoupper($request->e_phone),
            'e_address' => strtoupper($request->address),
            'credit_limit' => $request->credit_limit ?? 0,
            'credit_term_days' => $request->credit_term_days ?? 30,
        ];

        Customer::find($customer->id)->update($data);

        return redirect()->route('customers.show', $customer->id)->with('info', 'Customer successfully updated!');
    }

    protected function storeCustomerImage($file): string
    {
        $extension = $file->guessExtension();

        abort_unless(
            in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true),
            422,
            'Unsupported image format.'
        );

        $name = hexdec(uniqid()).'.'.$extension;
        $location = 'img/employees/';
        $file->move(public_path($location), $name);

        return $location.$name;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return Redirector|RedirectResponse
     */
    public function destroy(Customer $customer)
    {
        if (auth()->user()->role->cstmr_delete) {
            Customer::find($customer->id)->update([
                'status' => false,
            ]);

            return redirect()->route('customers.index')->with('success', 'Customer successfully deactivated!');
        }

        return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");
    }

    public function table(Request $request)
    {
        $q = Customer::query()->select('id', 'name', 'code', 'phone', 'accumulated_points')->where('status', true);

        return DataTables($q)
            ->addColumn('actions', function (Customer $customer) {
                $action = "<div class='d-flex justify-content-end flex-shrink-0'>";
                // View Button
                if (auth()->user()->role->cstmr_read) {
                    $action .= '<a href="'.route('customers.show', $customer->id).'" class="btn btn-icon btn-bg-light btn-active-color-primary btn-sm me-1" data-bs-toggle="tooltip" data-bs-placement="top" title="Details"><i class="fas fa-eye"></i></a>&nbsp';
                }
                if (auth()->user()->role->cstmr_update) {
                    // Edit Button
                    $action .= '<a href="'.route('customers.edit', $customer->id).'" class="btn btn-icon btn-bg-light btn-active-color-info btn-sm me-1" value="'.$customer->id.'" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit"><i class="fas fa-edit"></i></a>&nbsp';
                }
                if (auth()->user()->role->cstmr_delete) {
                    // Delete Button
                    $action .= '<span data-bs-toggle="modal" data-bs-target="#deleteModal"><button type="button" class="btn btn-icon btn-bg-light btn-active-color-danger btn-sm me-1" value="'.$customer->id.'" data-bs-toggle="tooltip" data-bs-placement="top" title="Delete"><i class="fas fa-trash"></i></button></span>';
                    $action .= '<input type="hidden" id="name_'.$customer->id.'" value="'.$customer->name.'" />';
                    $action .= '<form method="POST" action="'.route('customers.destroy', $customer->id).'" id="form_delete_'.$customer->id.'" value="'.$customer->name.'">'.method_field('DELETE').csrf_field().'</form>';
                }
                $action .= '</div>';

                return $action;
            })
            ->rawColumns(['actions'])
            ->make(true);
    }

    public function activate($id)
    {
        Customer::where('id', $id)->update([
            'status' => true,
        ]);

        return redirect()->route('customers.index')->with('msg', 'Customer successfully activated!');
    }

    public function generate_id($id)
    {
        $customer = Customer::find($id);

        return view('admin.customer-relations.customers.generate_id', compact('customer'));
    }

    public function membersReport()
    {
        return view('admin.reports.reports.customers.members');
    }

    public function nonMembersReport()
    {
        return view('admin.reports.reports.customers.non-members');
    }

    public function membersReportTable(Request $request)
    {
        $request->validate([
            'startDate' => ['required', 'date'],
            'endDate' => ['required', 'date'],
        ]);
        $startDate = Carbon::parse($request->startDate)->startOfDay();
        $endDate = Carbon::parse($request->endDate)->endOfDay();
        $q = Sale::whereBetween('created_at', [$startDate, $endDate])
            ->where('sale_type', false)
            ->where('customer_id', '<>', '0')
            ->with('customer')
            ->get();

        return DataTables($q)
            ->make(true);
    }

    public function nonMembersReportTable(Request $request)
    {
        $request->validate([
            'startDate' => ['required', 'date'],
            'endDate' => ['required', 'date'],
        ]);
        $startDate = Carbon::parse($request->startDate)->startOfDay();
        $endDate = Carbon::parse($request->endDate)->endOfDay();
        $q = Sale::whereBetween('created_at', [$startDate, $endDate])->where('sale_type', 0)->where('customer_id', '0')->get();

        return DataTables($q)
            ->make(true);
    }
}
