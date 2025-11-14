<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    /**
     * LIST ROLES
     * Endpoint used by: /admin/roles/list
     */
    public function index(Request $request)
    {
        $query = Role::with('permissions', 'users');

        // Search filter (case-insensitive)
        if ($request->search) {
            $searchTerm = strtolower($request->search);
            $query->whereRaw('LOWER(name) LIKE ?', ["%{$searchTerm}%"]);
        }

        $roles = $query
            ->orderBy('name')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'status' => 'success',
            'data'   => $roles
        ]);
    }

    /**
     * CREATE ROLE
     */
    public function store(Request $request)
    {
        $guardName = $request->guard_name ?? 'web';

        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles')->where(function ($q) use ($guardName) {
                    return $q->where('guard_name', $guardName);
                }),
            ],
            'guard_name'   => 'nullable|string|max:255',
            'permissions'  => 'nullable|array',
            'permissions.*'=> 'exists:permissions,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Please fix the errors below',
                'errors'  => $validator->errors()
            ], 422);
        }

        $role = Role::create([
            'name'       => $request->name,
            'guard_name' => $guardName,
        ]);

        // Sync permissions
        if ($request->permissions) {
            $role->permissions()->sync($request->permissions);
        } else {
            $role->permissions()->sync([]);
        }

        $role->load('permissions', 'users');

        return response()->json([
            'status'  => 'success',
            'message' => 'Role created successfully',
            'data'    => $role
        ], 201);
    }

    /**
     * SHOW ROLE
     * Endpoint: /admin/roles/{id}
     */
    public function show($id)
    {
        $role = Role::with('permissions', 'users')->find($id);

        if (!$role) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Role not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $role
        ]);
    }

    /**
     * UPDATE ROLE
     */
    public function update(Request $request, $id)
    {
        $role = Role::find($id);

        if (!$role) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Role not found'
            ], 404);
        }

        $guardName = $request->guard_name ?? 'web';

        $validator = Validator::make($request->all(), [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('roles')->where(function ($q) use ($guardName) {
                    return $q->where('guard_name', $guardName);
                })->ignore($role->id)
            ],
            'guard_name' => 'nullable|string|max:255',
            'permissions'=> 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Please fix the errors below',
                'errors'  => $validator->errors()
            ], 422);
        }

        // Update basic fields
        $role->name = $request->name;
        $role->guard_name = $request->guard_name ?? $role->guard_name;
        $role->save();

        // Sync permissions
        if ($request->has('permissions')) {
            $role->permissions()->sync($request->permissions);
        } else {
            $role->permissions()->sync([]);
        }

        $role->load('permissions', 'users');

        return response()->json([
            'status'  => 'success',
            'message' => 'Role updated successfully',
            'data'    => $role
        ]);
    }

    /**
     * DELETE ROLE
     */
    public function destroy($id)
    {
        $role = Role::find($id);

        if (!$role) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Role not found'
            ], 404);
        }

        $role->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Role deleted successfully'
        ]);
    }

    /**
     * GET PERMISSIONS OF ONE ROLE
     */
    public function getPermissions($id)
    {
        $role = Role::with('permissions')->find($id);

        if (!$role) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Role not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $role->permissions
        ]);
    }
}
