<?php

namespace App\Http\Controllers\Admin\CustomerRelations;

use App\Http\Controllers\Admin\HelperController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Exception;
use App\Http\Requests\SpecialCustomer\StoreRequest;
use App\Http\Requests\SpecialCustomer\UpdateRequest;
use App\Models\CustomerRelations\SpecialCustomer;

class SpecialCustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('admin.customer-relations.special_customers.index');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRequest $request)
    {
        $customer = SpecialCustomer::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Customer '.$customer->name.' created successfully!',
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(SpecialCustomer $specialCustomer)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRequest $request, SpecialCustomer $specialCustomer)
    {
        $specialCustomer->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Customer successfully updated!',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SpecialCustomer $specialCustomer)
    {
        $specialCustomer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Customer '.$specialCustomer->name.' deleted successfully!',
        ]);
    }

    public function table()
    {
        $helper = new HelperController;
        $q = SpecialCustomer::query();
        try {
            return DataTables($q)
                ->addColumn('actions', function (SpecialCustomer $specialCustomer) use ($helper) {
                    return $helper->actionButtonsReturnModal($specialCustomer, 'special_customers', 'Customer');
                })
                ->rawColumns(['actions'])
                ->make(true);
        } catch (Exception $e) {
            \response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }

        return [];
    }

    public function getCustomer(SpecialCustomer $customer)
    {
        return response()->json([
            'success' => true,
            'customer' => $customer,
        ]);
    }
}
