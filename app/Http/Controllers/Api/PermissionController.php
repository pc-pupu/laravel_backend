<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PermissionController extends Controller
{
    /**
     * Display a listing of permissions.
     */
    public function index(Request $request)
    {
        $query = Permission::with('roles');

        // Search by name or guard_name
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('guard_name', 'like', "%{$search}%");
            });
        }

        // Filter by guard_name
        if ($request->has('guard_name')) {
            $query->where('guard_name', $request->guard_name);
        }

        $permissions = $query->orderBy('guard_name')->orderBy('name')->paginate($request->get('per_page', 15));

        return response()->json([
            'status' => 'success',
            'data' => $permissions
        ]);
    }

    /**
     * Store a newly created permission.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:permissions,name',
            'guard_name' => 'nullable|string|max:255',
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
                            'The guard_name field is required.',
                        ],
                        [
                            'Permission name is required.',
                            'A permission with this name already exists.',
                            'Guard name is required.',
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

        $permission = Permission::create([
            'name' => $request->name,
            'guard_name' => $request->guard_name ?? 'web',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Permission created successfully',
            'data' => $permission
        ], 201);
    }

    /**
     * Display the specified permission.
     */
    public function show($id)
    {
        $permission = Permission::with('roles')->find($id);

        if (!$permission) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $permission
        ]);
    }

    /**
     * Update the specified permission.
     */
    public function update(Request $request, $id)
    {
        $permission = Permission::find($id);

        if (!$permission) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255', Rule::unique('permissions', 'name')->ignore($permission->id)],
            'guard_name' => 'nullable|string|max:255',
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
                            'The guard_name field is required.',
                        ],
                        [
                            'Permission name is required.',
                            'A permission with this name already exists.',
                            'Guard name is required.',
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

        $permission->name = $request->name;
        if ($request->has('guard_name')) {
            $permission->guard_name = $request->guard_name;
        }
        $permission->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Permission updated successfully',
            'data' => $permission
        ]);
    }

    /**
     * Remove the specified permission.
     */
    public function destroy($id)
    {
        $permission = Permission::find($id);

        if (!$permission) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission not found'
            ], 404);
        }

        $permission->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Permission deleted successfully'
        ]);
    }

    /**
     * Get all guard names for filtering
     */
    public function getModules()
    {
        $guards = Permission::distinct()->pluck('guard_name')->filter()->sort()->values();

        return response()->json([
            'status' => 'success',
            'data' => $guards
        ]);
    }
}

