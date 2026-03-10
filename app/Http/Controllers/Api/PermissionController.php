<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Services\ErrorLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PermissionController extends Controller
{
    /**
     * LIST PERMISSIONS
     * Called from permissions.js → loadPermissions()
     * Endpoint: /admin/permissions/list
     */
    public function index(Request $request)
    {
        $query = Permission::with('roles');

        // Search: name or guard_name (case-insensitive)
        if ($request->search) {
            $searchTerm = strtolower($request->search);
            $query->where(function ($q) use ($searchTerm) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$searchTerm}%"])
                  ->orWhereRaw('LOWER(guard_name) LIKE ?', ["%{$searchTerm}%"]);
            });
        }

        // Filter by guard name
        if ($request->guard_name) {
            $query->where('guard_name', $request->guard_name);
        }

        $permissions = $query
            ->orderBy('guard_name')
            ->orderBy('name')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'status' => 'success',
            'data'   => $permissions
        ]);
    }

    /**
     * CREATE PERMISSION
     * Called from permissions.js → savePermission()
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'       => 'required|string|max:255|unique:permissions,name',
            'guard_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Please fix the errors below',
                'errors'  => $validator->errors()
            ], 422);
        }

        try {
            $perm = ErrorLogService::wrap(function () use ($request) {
                return Permission::create([
                    'name'       => $request->name,
                    'guard_name' => $request->guard_name ?? 'web',
                ]);
            }, ['module' => 'permissions', 'action' => 'create']);
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to create permission.',
            ], 500);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Permission created successfully',
            'data'    => $perm
        ], 201);
    }

    /**
     * SHOW PERMISSION
     * Called from permissions.js → editPermission()
     */
    public function show($id)
    {
        $permission = Permission::with('roles')->find($id);

        if (!$permission) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Permission not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $permission
        ]);
    }

    /**
     * UPDATE PERMISSION
     */
    public function update(Request $request, $id)
    {
        $permission = Permission::find($id);

        if (!$permission) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Permission not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'       => ['required', 'string', 'max:255', Rule::unique('permissions')->ignore($permission->id)],
            'guard_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Please fix the errors below',
                'errors'  => $validator->errors()
            ], 422);
        }

        try {
            ErrorLogService::wrap(function () use ($permission, $request) {
                $permission->name = $request->name;
                $permission->guard_name = $request->guard_name ?? $permission->guard_name;
                $permission->save();
            }, ['module' => 'permissions', 'action' => 'update', 'permission_id' => $permission->id]);
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to update permission.',
            ], 500);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Permission updated successfully',
            'data'    => $permission
        ]);
    }

    /**
     * DELETE PERMISSION
     * Called from permissions.js → deletePermission()
     */
    public function destroy($id)
    {
        $permission = Permission::find($id);

        if (!$permission) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Permission not found'
            ], 404);
        }

        try {
            ErrorLogService::wrap(function () use ($permission) {
                // Laravel will auto-delete pivot relationships
                $permission->delete();
            }, ['module' => 'permissions', 'action' => 'delete', 'permission_id' => $permission->id]);
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to delete permission.',
            ], 500);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Permission deleted successfully'
        ]);
    }

    /**
     * RETURN ALL UNIQUE GUARD NAMES
     * Used by permissions.js → loadGuards()
     */
    public function getModules()
    {
        $guards = Permission::distinct()
            ->pluck('guard_name')
            ->filter()
            ->sort()
            ->values();

        return response()->json([
            'status' => 'success',
            'data'   => $guards
        ]);
    }
}
