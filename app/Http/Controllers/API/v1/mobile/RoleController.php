<?php

namespace App\Http\Controllers\API\v1\mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Role\StoreRequest;
use App\Http\Requests\Role\UpdateRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Employees\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $roles = Role::where('status', 1)
            ->when($request->term, function ($query) use ($request) {
                $query->where('name', 'like', '%'.$request->term.'%');
            })
            ->orderBy('name')
            ->get();

        return $this->success(['roles' => $roles]);
    }

    public function store(StoreRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['status'] = true;
        $validated['user_id'] = auth()->user()->user_id;

        $role = Role::create($validated);

        return $this->created([
            'role' => $role,
        ], 'Role created successfully!');
    }

    public function show(Role $role): JsonResponse
    {
        return $this->success(['role' => $role]);
    }

    public function update(UpdateRequest $request, Role $role): JsonResponse
    {
        $validated = $request->validated();
        $role->update($validated);

        return $this->success([
            'role' => $role->fresh(),
        ], 'Role updated successfully!');
    }

    public function destroy(Role $role): JsonResponse
    {
        $role->update(['status' => false]);

        return $this->success(null, 'Role deleted successfully!');
    }
}
