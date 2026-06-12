<?php

namespace App\Http\Controllers\API\v1\pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\v1\pos\Authentication\StoreRequest;
use App\Http\Traits\ApiResponse;
use App\Jobs\API\v1\Authentication\ProcessRequestJob;
use App\Models\Authentication;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthenticationController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $authentication = Authentication::create($validated);
        // create a delayed job dispatch of 3 minutes.
        ProcessRequestJob::dispatch($authentication)->delay(now()->addMinutes(3));

        return $this->success(null, 'Waiting for consignee to approve the request.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Authentication $authentication)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Authentication $authentication)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Authentication $authentication)
    {
        //
    }

    public function getUserWithRole(Request $request): JsonResponse
    {
        $request->validate([
            'role' => ['required', 'string'],
        ]);

        $users = User::leftJoin('roles', 'role_id', '=', 'roles.id')
            ->where(function ($q) use ($request) {
                /*
                 * Role: role
                 * discounts = User is allowed to use Discounts Feature
                 * delete_items = User is allowed to use Delete Item from Tickets Feature
                 * rfnd = User is allowed to use Refund Feature
                 */
                $role = $request->role;
                switch ($role) {
                    case 'discounts':
                        $q->where('discounts', true);
                        break;
                    case 'delete_items':
                        $q->where('delete_items', true);
                        break;
                    case 'rfnd':
                        $q->where('rfnd', true);
                        break;
                    case 'crdt_sale':
                        $q->where('crdt_sale', true);
                        break;
                    default:
                        $q->where('pos', 3);
                        break;
                }
            })
            ->select([
                'users.id',
                \DB::raw('users.name as user_name'),
                'role_id',
                \DB::raw('roles.name as role_name'),
                'rfnd',
                'discounts',
                'delete_items',
                'csh_out',
                'crdt_sale',
                'crdt_pymnt',
            ])
            ->get();

        return $this->success([
            'users' => $users,
        ]);
    }
}
