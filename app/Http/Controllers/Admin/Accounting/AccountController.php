<?php

namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\StoreRequest;
use App\Http\Requests\Account\UpdateRequest;
use App\Models\Accounting\Account;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(): View
    {
        return view('admin.accounting.accounts.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreRequest $request)
    {
        $validated = $request->validated();
        if (! $validated['number']) {
            $latest_account = Account::where('type', $validated['type'])->latest()->first();
            $validated['number'] = $latest_account ? $latest_account->number + 1 : 0;
        }

        $validated['name'] = ucwords($validated['name']);
        $account = Account::create($validated);

        return response([
            'success' => true,
            'message' => "{$account['name']} account successfully created!",
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Account  $account
     * @return \Illuminate\Http\Response
     */
    public function show(Account $account)
    {
        return response($account);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Account  $account
     * @return \Illuminate\Http\Response
     */
    public function edit(Account $account)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Account  $account
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateRequest $request, Account $account)
    {
        $validated = $request->validated();
        if (! $validated['number']) {
            $latest_account = Account::where('type', $validated['type'])->latest()->first();
            $validated['number'] = $latest_account ? $latest_account->number + 1 : 0;
        }

        $validated['name'] = ucwords($validated['name']);
        $account->update($validated);

        return response([
            'success' => true,
            'message' => "{$account['name']} account successfully updated!",
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Account  $account
     * @return \Illuminate\Http\Response
     */
    public function destroy(Account $account)
    {
        //
    }

    public function table(Request $request)
    {
        $q = Account::query();

        return DataTables($q)
            ->addColumn('actions', function (Account $account) {
                $action = "<div class='d-flex justify-content-end flex-shrink-0'>";
                // View Button
                $action .= '<span data-bs-toggle="modal" data-bs-target="#showAccount"><button class="btn btn-icon btn-bg-light btn-active-color-primary btn-sm me-1" data-bs-toggle="tooltip" data-bs-placement="top" title="View"><i class="fas fa-eye"></i></button></span>&nbsp';
                // Edit Button
                $action .= '<span data-bs-toggle="modal" data-bs-target="#accountModal"><button class="btn btn-icon btn-bg-light btn-active-color-info btn-sm me-1" value="'.$account->id.'" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit"><i class="fas fa-edit"></i></button></span>&nbsp';
                // Delete Button
                $action .= '<span data-bs-toggle="modal" data-bs-target="#deleteAccountModal"><button type="button" class="btn btn-icon btn-bg-light btn-active-color-danger btn-sm me-1" value="'.$account->id.'" data-bs-toggle="tooltip" data-bs-placement="top" title="Delete"><i class="fas fa-trash"></i></button></span>';
                $action .= "\n<input type='hidden' id='name_$account->id' value='$account->name' />
                    ";
                $action .= '</div>';

                return $action;
            })
            ->rawColumns(['actions'])
            ->make(true);
    }
}
