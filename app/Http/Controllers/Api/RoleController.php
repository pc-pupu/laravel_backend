<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Log;

class RoleController extends Controller
{
    /**
     * Display a listing of roles.
     */
    public function index(Request $request)
    {
        $query = Role::with('permissions', 'users');

        // Search by name
        if ($request->has('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        $roles = $query->orderBy('name')->paginate($request->get('per_page', 15));
        Log::info('Roles fetched: ', ['roles' => $roles->items()]);

        return response()->json([
            'status' => 'success',
            'data' => $roles
        ]);
    }

    /**
     * Store a newly created role.
     */
    public function store(Request $request)
    {
        $guardName = $request->guard_name ?? 'web';
        
        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles')->where(function ($query) use ($guardName) {
                    return $query->where('guard_name', $guardName);
                })
            ],
            'guard_name' => 'nullable|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        if ($validator->fails()) {
            // Convert validation errors to user-friendly messages
            $friendlyErrors = [];
            $errors = $validator->errors();
            
            // Convert each error to user-friendly format
            foreach ($errors->messages() as $field => $messages) {
                $friendlyErrors[$field] = array_map(function($message) {
                    return str_replace(
                        [
                            'The name field is required.',
                            'The name has already been taken.',
                            'The permissions.0 must exist.',
                            'The selected permissions.0 is invalid.',
                        ],
                        [
                            'Role name is required.',
                            'A role with this name already exists for this guard.',
                            'One or more selected permissions are invalid.',
                            'One or more selected permissions are invalid.',
                        ],
                        $message
                    );
                }, $messages);
            }
            
            return response()->json([
                'status' => 'error',
                'message' => 'Please fix the errors below',
                'errors' => $friendlyErrors
            ], 422);
        }

        $role = Role::create([
            'name' => $request->name,
            'guard_name' => $request->guard_name ?? 'web',
        ]);

        // Assign permissions (empty array means no permissions)
        if ($request->has('permissions') && is_array($request->permissions)) {
            $role->permissions()->sync($request->permissions);
        } else {
            // If permissions not provided, sync empty array
            $role->permissions()->sync([]);
        }

        $role->load('permissions');

        return response()->json([
            'status' => 'success',
            'message' => 'Role created successfully',
            'data' => $role
        ], 201);
    }

    /**
     * Display the specified role.
     */
    public function show($id)
    {
        $role = Role::with('permissions', 'users')->find($id);

        if (!$role) {
            return response()->json([
                'status' => 'error',
                'message' => 'Role not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $role
        ]);
    }

    /**
     * Update the specified role.
     */
    public function update(Request $request, $id)
    {
        $role = Role::find($id);

        if (!$role) {
            return response()->json([
                'status' => 'error',
                'message' => 'Role not found'
            ], 404);
        }

        $guardName = $request->guard_name ?? 'web';
        
        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles')->where(function ($query) use ($guardName) {
                    return $query->where('guard_name', $guardName);
                })->ignore($role->id, 'id')
            ],
            'guard_name' => 'nullable|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        if ($validator->fails()) {
            // Convert validation errors to user-friendly messages
            $friendlyErrors = [];
            $errors = $validator->errors();
            
            // Convert each error to user-friendly format
            foreach ($errors->messages() as $field => $messages) {
                $friendlyErrors[$field] = array_map(function($message) {
                    return str_replace(
                        [
                            'The name field is required.',
                            'The name has already been taken.',
                            'The permissions.0 must exist.',
                            'The selected permissions.0 is invalid.',
                        ],
                        [
                            'Role name is required.',
                            'A role with this name already exists for this guard.',
                            'One or more selected permissions are invalid.',
                            'One or more selected permissions are invalid.',
                        ],
                        $message
                    );
                }, $messages);
            }
            
            return response()->json([
                'status' => 'error',
                'message' => 'Please fix the errors below',
                'errors' => $friendlyErrors
            ], 422);
        }

        $role->name = $request->name;
        if ($request->has('guard_name')) {
            $role->guard_name = $request->guard_name;
        }
        $role->save();

        // Update permissions (empty array means no permissions)
        if ($request->has('permissions') && is_array($request->permissions)) {
            $role->permissions()->sync($request->permissions);
        } else {
            // If permissions not provided, sync empty array
            $role->permissions()->sync([]);
        }

        $role->load('permissions');

        return response()->json([
            'status' => 'success',
            'message' => 'Role updated successfully',
            'data' => $role
        ]);
    }

    /**
     * Remove the specified role.
     */
    public function destroy($id)
    {
        $role = Role::find($id);

        if (!$role) {
            return response()->json([
                'status' => 'error',
                'message' => 'Role not found'
            ], 404);
        }

        $role->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Role deleted successfully'
        ]);
    }

    /**
     * Get all permissions for role assignment
     */
    public function getPermissions($id)
    {
        $role = Role::with('permissions')->find($id);
        
        if (!$role) {
            return response()->json([
                'status' => 'error',
                'message' => 'Role not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $role->permissions
        ]);
    }
}

