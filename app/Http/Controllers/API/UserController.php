<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Jobs\API\v1\PosLog\PosLogJob;
use App\Models\Accounting\PosLog;
use App\Models\Employees\Role;
use App\Models\Pos\Receipt;
use App\Models\Products\Item;
use App\Models\Settings\Pos;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    use ApiResponse;

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
            'mac' => ['required', 'string'],
        ]);
        if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            // Get User Details
            $user = Auth::user();
            $userDetails = User::where('id', $user->id)->with([
                'role',
            ])->first();
            // Get Receipt Details
            $receipt = Receipt::first();
            // Check for POS Details and Availability
            $pos = Pos::where('mac', $request->mac)
                ->where('status', true)
                ->with('store')
                ->get();
            if (count($pos) == 0) {
                // Get Available POS
                $availablePOS = Pos::where('status', false)
                    ->with('store')
                    ->first();
                if ($availablePOS) {
                    // Assign the first available POS to the device
                    $availablePOS->update([
                        'mac' => $request->mac,
                        'status' => true,
                    ]);
                    $pos = Pos::where('mac', $request->mac)
                        ->where('status', true)
                        ->with('store')
                        ->get();
                    $log = PosLog::create([
                        'cash_in' => null,
                        'rendered' => null,
                        'cash_out' => null,
                        'type' => $request->pos_log['type'],
                        'reason' => $request->pos_log['reason'],
                        'so_id' => null,
                        'pos_id' => $pos[0]->id,
                        'store_id' => $pos[0]->store->id,
                        'user_id' => $user->id,
                    ]);

                    return $this->success([
                        'token' => $user->createToken('appToken')->accessToken,
                        'user' => $userDetails,
                        'pos' => $pos,
                        'receipt' => $receipt,
                        'log' => $log,
                    ]);
                } else {
                    return $this->error('There are no available POS. Please contact administrator.');
                }
            }
            // Log Data
            PosLogJob::dispatch(
                null,
                null,
                null,
                1,
                'Log-In - '.$user->name,
                null,
                $pos[0]->id,
                $pos[0]->store->id,
                $user->id,
            );

            return $this->success([
                'token' => $user->createToken('appToken')->accessToken,
                'user' => $userDetails,
                'pos' => $pos,
                'receipt' => $receipt,
            ]);
        } else {
            return $this->error('Invalid Email or Password', 401);
        }
    }

    public function logout(Request $res): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return $this->error('Unable to Logout');
        }

        $user->token()->revoke();

        $pos = Pos::where('mac', $res->input('mac'))
            ->where('status', true)
            ->first();

        if ($pos) {
            PosLogJob::dispatch(
                null,
                null,
                null,
                8,
                'Logged Out | User: '.$user->name,
                null,
                $pos->id,
                $pos->store_id,
                $user->id,
            );
        }

        return $this->success(null, 'Logout successful');
    }

    public function items(): JsonResponse
    {
        $items = Item::all()->take(100);

        return $this->success($items);
    }

    public function getUser(): JsonResponse
    {
        $user = Auth::guard('api')->user();
        $userDetails = User::where('id', $user->id)->with([
            'role',
        ])->first();

        return $this->success([
            'token' => $user->createToken('appToken')->accessToken,
            'user' => $userDetails,
        ]);
    }

    public function higher_access(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);
        $user = User::where('email', $request->email)->first();
        if ($user) {
            if (\Hash::check($request->password, $user->password)) {
                $userDetails = User::where('id', $user->id)->with([
                    'role',
                ])->first();

                return $this->success(['user' => $userDetails], 'Authentication success!');
            } else {
                return $this->error('Wrong credentials', 401);
            }
        } else {
            return $this->notFound('User not found!');
        }
    }

    public function verifyUiniqid(Request $request): JsonResponse
    {
        $id = $request->key;
        $type = $request->type;
        $user = User::where('uniqid', $id)->first();
        $role = Role::where('id', $user->role_id)
            ->where($type, true)
            ->first();

        return $this->success(['verified' => $role->$type]);
    }
}
