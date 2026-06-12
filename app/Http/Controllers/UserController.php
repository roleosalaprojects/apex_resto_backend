<?php

namespace App\Http\Controllers;

use App\Models\Accounting\PosLog;
use App\Models\Employees\Employee;
use App\Models\Employees\EmployeeStore;
use App\Models\Employees\Role;
use App\Models\Pos\Sale;
use App\Models\Settings\Store;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        $access = Role::find(auth()->user()->role_id);
        if ($access->emplys) {
            return view('admin.employees.index', compact('access'));
        }

        return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Factory|Redirector|\Illuminate\View\View|RedirectResponse|View
     */
    public function create()
    {
        //
        $access = Role::find(auth()->user()->role_id);
        if ($access->emplys_create) {
            $user = new User;
            $roles = Role::where('name', '<>', 'OWNER')->where('name', '<>', 'BIR')
                ->where('user_id', auth()->user()->user_id)
                ->get();
            $selected_role = 0;

            $stores = Store::where('user_id', auth()->user()->user_id)->where('status', true)->get();

            return view('admin.employees.create', compact('user', 'roles', 'selected_role', 'access', 'stores'));
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
        //
        // dd($request->all());
        $user = User::where('email', $request->email)->get();
        if (! $user) {
            return redirect()->back()->with('danger-callout', 'Email has been taken. Please try again')->withInput();
        }
        $request->validate([
            'name' => 'required',
            'role' => 'required',
            'phone' => 'required',
            'address' => 'required',
            'email' => ['required', 'unique:users,email'],
            'password' => 'required|confirmed',
            'image' => 'nullable|mimes:jpg,png,jpeg,gif,svg',
            'stores' => 'required|array',
            'stores.*' => 'required|string',
            'code' => 'nullable|string|max:100',
        ]);

        $last_image = '';
        if ($request->file('image')) {
            // Usual
            $brand_image = $request->file('image');
            $name_gen = hexdec(uniqid());
            $img_ext = strtolower($brand_image->getClientOriginalExtension());
            $img_name = $name_gen.'.'.$img_ext;
            $up_location = 'img/employees/';
            $last_image = $up_location.$img_name;
            $brand_image->move($up_location, $last_image);
            $last_image = $up_location.$name_gen;
        }

        $employee = User::create([
            'name' => strtoupper($request->name),
            'role_id' => $request->role,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'status' => true,
            'user_id' => auth()->user()->user_id,
            'uniqid' => uniqid(),
            'code' => $request->code,
            // 'schedule_id'=>$request->schedule,
            // 'deduction'=>($request->deduction) ? true : false
        ]);
        Employee::create([
            'phone' => strtoupper($request->phone),
            'address' => strtoupper($request->address),
            'status' => true,
            'user_id' => $employee->id,
            'image' => $last_image,
        ]);
        for ($i = 0; $i < count($request->stores); $i++) {
            EmployeeStore::create([
                'status' => true,
                'store_id' => $request->stores[$i],
                'user_id' => $employee->id,
            ]);
        }

        return redirect()->route('employees.index')->with('success', 'A new Employee has been created!');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\User  $user
     * @return \Illuminate\Http\Response
     */
    public function show(User $user, $id)
    {
        $access = Role::find(auth()->user()->role_id);
        if ($access->emplys_read) {
            $employee = Employee::where('user_id', $id)->first();
            $overallSales = Sale::where('sales_by', $id)->where('type', false)->sum('total');
            $overallRefunds = Sale::where('sales_by', $id)->where('type', true)->sum('total');

            return view('admin.employees.show', compact('access', 'employee', 'overallSales', 'overallRefunds'));
        }

        return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\User  $user
     * @return \Illuminate\Http\Response
     */
    public function edit(User $user, $id)
    {
        //
        $access = Role::find(auth()->user()->role_id);
        if ($access->emplys_update) {
            $user = DB::table('users as u')
                ->leftJoin('employees as e', 'e.user_id', 'u.id')
                ->where('u.id', $id)
                ->first();
            $roles = Role::where('name', '<>', 'OWNER')
                ->where('user_id', auth()->user()->user_id)
                ->get();
            $selected_role = $user->role_id;
            $stores = Store::where('user_id', auth()->user()->user_id)->where('status', true)->get();
            $employeeStores = EmployeeStore::where('user_id', $id)->get();

            return view('admin.employees.edit', compact('user', 'roles', 'selected_role', 'access', 'stores', 'employeeStores'));
        }

        return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\User  $user
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, User $user, $id)
    {
        //
        $user = User::where('email', $request->email)->where('id', '<>', $id)->get();
        if (! $user) {
            return redirect()->back()->with('danger-callout', 'Email has been taken. Please try again')->withInput();
        }
        $request->validate([
            'name' => 'required',
            'role' => 'required',
            'phone' => 'required',
            'address' => 'required',
            'email' => 'required',
            'stores' => 'required|array',
            'stores.*' => 'required|string',
            'code' => 'nullable|string|max:100',
        ]);
        User::find($id)->update([
            'name' => strtoupper($request->name),
            'role_id' => $request->role,
            'email' => $request->email,
            'code' => $request->code,
            // 'schedule_id'=>$request->schedule,
            // 'deduction'=>($request->deduction) ? true : false
        ]);
        $old_image = $request->old_image;

        $brand_image = $request->file('image');
        if ($brand_image) {
            $name_gen = hexdec(uniqid());
            $img_ext = strtolower($brand_image->getClientOriginalExtension());
            $img_name = $name_gen.'.'.$img_ext;
            $up_location = 'img/employees/';
            $last_image = $up_location.$img_name;
            $brand_image->move($up_location, $last_image);
            if ($request->old_image) {
                if (file_exists($old_image)) {
                    unlink($old_image);
                }
            }
            Employee::where('user_id', $id)->update([
                'phone' => strtoupper($request->phone),
                'address' => strtoupper($request->address),
                'image' => $last_image,
            ]);
        } else {
            Employee::where('user_id', $id)->update([
                'phone' => strtoupper($request->phone),
                'address' => strtoupper($request->address),
            ]);
        }
        EmployeeStore::where('user_id', $id)->delete();
        for ($i = 0; $i < count($request->stores); $i++) {
            EmployeeStore::create([
                'status' => true,
                'store_id' => $request->stores[$i],
                'user_id' => $id,
            ]);
        }

        return redirect()->route('employees.index')->with('info', 'Employee successfully updated!');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\User  $user
     * @return \Illuminate\Http\Response
     */
    public function destroy(User $user, $id)
    {
        // dd($id);
        $access = Role::find(auth()->user()->role_id);
        if ($access->emplys_delete) {
            $user = User::find($id);
            $name = $user->name;
            $user->update(['status' => false]);

            return redirect()->route('employees.index')->with('success', 'You have successfully deactivated user : '.$user->name);
        }

        return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");

    }

    public function activate($id)
    {
        $access = Role::find(auth()->user()->role_id);
        if ($access->emplys_delete) {
            User::where('id', $id)->update(['status' => true]);
            $user = User::where('id', $id)->first();

            return redirect()->route('employees.index')->with('msg', 'You have successfully activated user : '.$user->name);
        }

        return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");

    }

    /**
     * POS log type labels.
     *
     * @var array<int, string>
     */
    private const TYPE_LABELS = [
        1 => 'Login',
        2 => 'Store Selection',
        3 => 'Start Day',
        4 => 'Cash-In',
        5 => 'Sale',
        6 => 'Refund',
        7 => 'Lock',
        8 => 'Log-Out',
        9 => 'Unlocked',
        10 => 'Z-Reading',
        11 => 'X-Reading',
        12 => 'Cash-Out',
        13 => 'Void Cash-Out',
        14 => 'Shift Reading',
    ];

    /**
     * Badge colors per type.
     *
     * @var array<int, string>
     */
    private const TYPE_COLORS = [
        1 => 'primary',
        2 => 'info',
        3 => 'success',
        4 => 'success',
        5 => 'primary',
        6 => 'warning',
        7 => 'secondary',
        8 => 'dark',
        9 => 'info',
        10 => 'danger',
        11 => 'warning',
        12 => 'danger',
        13 => 'danger',
        14 => 'info',
    ];

    /**
     * Icon classes per type.
     *
     * @var array<int, string>
     */
    private const TYPE_ICONS = [
        1 => 'ki-outline ki-entrance-left',
        2 => 'ki-outline ki-shop',
        3 => 'ki-outline ki-sun',
        4 => 'ki-outline ki-dollar',
        5 => 'ki-outline ki-handcart',
        6 => 'ki-outline ki-arrow-left',
        7 => 'ki-outline ki-lock-2',
        8 => 'ki-outline ki-exit-left',
        9 => 'ki-outline ki-lock-3',
        10 => 'ki-outline ki-document',
        11 => 'ki-outline ki-chart-simple',
        12 => 'ki-outline ki-wallet',
        13 => 'ki-outline ki-cross-circle',
        14 => 'ki-outline ki-time',
    ];

    public function timeline(Request $request): JsonResponse
    {
        $appTz = config('app.timezone');
        $startDate = Carbon::parse($request->startDate, $appTz)->startOfDay()->utc()->toDateTimeString();
        $endDate = Carbon::parse($request->endDate, $appTz)->endOfDay()->utc()->toDateTimeString();

        $logs = PosLog::with(['sales:id,total,son', 'pos:id,name', 'store:id,name'])
            ->where('user_id', $request->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('id', 'DESC')
            ->get();

        $grouped = $logs->groupBy(function (PosLog $log) use ($appTz) {
            return Carbon::parse($log->getRawOriginal('created_at'), 'UTC')
                ->setTimezone($appTz)
                ->format('Y-m-d');
        });

        $result = [];
        foreach ($grouped as $date => $entries) {
            $result[] = [
                'date_group' => Carbon::parse($date)->format('M d, Y'),
                'date_raw' => $date,
                'entries' => $entries->map(function (PosLog $log) use ($appTz) {
                    $localTime = Carbon::parse($log->getRawOriginal('created_at'), 'UTC')
                        ->setTimezone($appTz);

                    return [
                        'id' => $log->id,
                        'type' => $log->type,
                        'type_label' => self::TYPE_LABELS[$log->type] ?? 'Unknown',
                        'type_color' => self::TYPE_COLORS[$log->type] ?? 'secondary',
                        'type_icon' => self::TYPE_ICONS[$log->type] ?? 'ki-outline ki-information',
                        'reason' => $log->reason,
                        'cash_in' => $log->cash_in ? number_format($log->cash_in, 2) : null,
                        'cash_out' => $log->cash_out ? number_format($log->cash_out, 2) : null,
                        'rendered' => $log->rendered ? number_format($log->rendered, 2) : null,
                        'sale_son' => $log->sales?->son,
                        'sale_total' => $log->sales ? number_format($log->sales->total, 2) : null,
                        'sale_id' => $log->sales?->id,
                        'pos_name' => $log->pos?->name,
                        'store_name' => $log->store?->name,
                        'time' => $localTime->format('h:i A'),
                    ];
                })->values(),
            ];
        }

        return response()->json($result);
    }

    public function getSelectedDate(Request $request)
    {
        // $output = $request->date;
        $user = User::find($request->name);
        $timeline = PosLog::with([
            'sales' => function ($q) {
                $q->select(
                    'id',
                    'total',
                    'son'
                );
            },
            'pos' => function ($q) {
                $q->select('id', 'name');
            },
            'store' => function ($q) {
                $q->select('id', 'name');
            },
        ])
            ->where('user_id', $user->id)
            ->whereDate('created_at', Carbon::parse($request->date))->orderBy('id', 'DESC')
            ->get();

        // return response($timeline);

        $output = '
        <div class="time-label">
            <span class="bg-red">'.date('M d, Y', strtotime($request->date)).'</span>
        </div>';
        foreach ($timeline as $time) {
            $output .= '<div>';
            if ($time->type == 1) {
                $output .= '<i class="fas fa-sign-in-alt bg-green"></i>';
            }
            if ($time->type == 2) {
                $output .= '<i class="fas fa-cash-register bg-blue"></i>';
            }

            if ($time->type == 3) {
                $output .= '<i class="fas fa-sun bg-yellow"></i>';
            }

            if ($time->type == 4) {
                $output .= '<i class="fas fa-money-bill-wave"></i>';
            }

            if ($time->type == 5) {
                $output .= '<i class="fas fa-file-invoice-dollar bg-green"></i>';
            }

            if ($time->type == 6) {
                $output .= '<i class="fas fa-hand-holding-usd bg-red"></i>';
            }

            if ($time->type == 7) {
                $output .= '<i class="fas fa-lock bg-purple"></i>';
            }

            if ($time->type == 8) {
                $output .= '<i class="fas fa-sign-out-alt bg-gray"></i>';
            }

            if ($time->type == 9) {
                $output .= '<i class="fas fa-unlock bg-orange"></i>';
            }

            $output .= '<div class="timeline-item">
            <span class="time"><i class="fas fa-clock"></i>'.date('h:i:s A', strtotime($time->created_at)).'</span> <h3 class="timeline-header">'.$time->reason.'</h3>';
            if ($time->type == 4 || $time->type == 5 || $time->type == 6 || $time->type == 8) {
                $output .= '<div class="timeline-body"> ';
                switch ($time->type) {
                    case 4:
                        $output .= '₱&nbsp'.number_format($time->cash_in, 2);
                        break;
                    case 5:
                        $output .= "Invoice #: <a href='".route('show.receipts', $time->sales->id)."' target='_blank'>".$time->sales->son.'</a>'.'&nbsp-&nbsp₱&nbsp'.number_format($time->sales->total, 2);
                        break;
                    case 6:
                        $output .= "Invoice #: <a href='".route('show.receipts', $time->sales->id)."' target='_blank'>".$time->sales->son.'</a>'.'&nbsp-&nbsp₱&nbsp'.number_format($time->sales->total, 2);
                        break;
                    case 8:
                        $output .= '₱&nbsp'.number_format($time->cash_out, 2);
                        break;
                    default:
                        break;
                }
                $output .= '</div>';
            }
            $output .= '</div></div>';
            $output .= '</div>';
        }

        $output .= '</div><div>
        <i class="fas fa-clock bg-gray"></i>
      </div>';

        return response($output);
    }

    public function GetTotalForSelectedDate(Request $request)
    {
        $user = User::find($request->name);
        $total_sales = DB::table('sales as s')
            ->where('sales_by', $user->id)
            ->where('type', 0)
            ->whereBetween('created_at', [Carbon::parse($request->date)->startOfDay()->format('Y-m-d H:m:s'), Carbon::parse($request->date)->endOfDay()->format('Y-m-d H:m:s')])
            ->sum('total');
        $total_refunds = DB::table('sales as s')
            ->where('sales_by', $user->id)
            ->where('type', 1)
            ->whereBetween('created_at', [Carbon::parse($request->date)->startOfDay()->format('Y-m-d H:m:s'), Carbon::parse($request->date)->endOfDay()->format('Y-m-d H:m:s')])
            ->sum('total');
        $total = $total_sales - $total_refunds;

        return Response($total);
    }

    public function table(Request $request)
    {
        $q = User::with([
            'position' => function ($q) {
                $q->select('id', 'name');
            },
            'details' => function ($q) {
                $q->select('user_id', 'image');
            },
        ])
            ->select(
                'id',
                'name',
                'role_id',
                'code',
            )
            ->where('status', true)
            ->get();

        return DataTables($q)
            ->addColumn('actions', function (User $user) {
                $action = "<div class='d-flex justify-content-end flex-shrink-0'>";
                // View Button
                if (auth()->user()->role->emplys_read) {
                    $action .= '<a href="'.route('employees.show', $user->id).'" class="btn btn-icon btn-bg-light btn-active-color-primary btn-sm me-1" data-bs-toggle="tooltip" data-bs-placement="top" title="Details"><i class="fas fa-eye"></i></a>&nbsp';
                }
                if (auth()->user()->role->emplys_update) {
                    // Edit Button
                    $action .= '<a href="'.route('employees.edit', $user->id).'" class="btn btn-icon btn-bg-light btn-active-color-info btn-sm me-1" value="'.$user->id.'" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit"><i class="fas fa-edit"></i></a>&nbsp';
                }
                if (auth()->user()->role->emplys_delete) {
                    // Delete Button
                    $action .= '<span data-bs-toggle="modal" data-bs-target="#deleteModal"><button type="button" class="btn btn-icon btn-bg-light btn-active-color-danger btn-sm me-1" value="'.$user->id.'" data-bs-toggle="tooltip" data-bs-placement="top" title="Delete"><i class="fas fa-trash"></i></button></span>';
                    $action .= '<input type="hidden" id="name_'.$user->id.'" value="'.$user->name.'" />';
                    $action .= '<form method="POST" action="'.route('employees.destroy', $user->id).'" id="form_delete_'.$user->id.'" value="'.$user->name.'">'.method_field('DELETE').csrf_field().'</form>';
                }
                $action .= '</div>';

                return $action;
            })
            ->rawColumns(['actions'])
            ->make(true);
    }
}
