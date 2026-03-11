<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HousingSidebarMenu;
use App\Services\ErrorLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class SidebarMenuController extends Controller
{
    /**
     * Get sidebar menus for authenticated user's roles
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 401);
        }

        // \Log::info('Fetching sidebar menus for user ID: ' . $user);
        // Get user's role IDs
        // Qualify column: both roles and user_role have 'rid', so use roles.rid
        $roleIds = $user->roles()->pluck('roles.rid')->toArray();

        if (empty($roleIds)) {
            return response()->json([
                'status' => 'success',
                'data' => []
            ]);
        }

        // Get parent menus with their children for user's roles
        $menus = HousingSidebarMenu::active()
            ->parents()
            ->forRoles($roleIds)
            ->with(['children' => function ($query) use ($roleIds) {
                $query->active()
                    ->forRoles($roleIds)
                    ->orderBy('order_no');
            }])
            ->orderBy('order_no')
            ->get()
            ->map(function ($menu) {
                return [
                    'sidebar_menu_id' => $menu->sidebar_menu_id,
                    'menu_name' => $menu->menu_name,
                    'route_name' => $menu->route_name,
                    'url' => $menu->url,
                    'icon_class' => $menu->icon_class,
                    'parent_id' => $menu->parent_id,
                    'order_no' => $menu->order_no,
                    'route_params' => $menu->route_params ?? [],
                    'has_submenu' => $menu->children->count() > 0,
                    'children' => $menu->children->map(function ($child) {
                        return [
                            'sidebar_menu_id' => $child->sidebar_menu_id,
                            'menu_name' => $child->menu_name,
                            'route_name' => $child->route_name,
                            'url' => $child->url,
                            'icon_class' => $child->icon_class,
                            'parent_id' => $child->parent_id,
                            'order_no' => $child->order_no,
                            'route_params' => $child->route_params ?? [],
                        ];
                    })->toArray(),
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => $menus
        ]);
    }

    /**
     * Get all menus (for admin management)
     */
    public function all(Request $request)
    {
        $menus = HousingSidebarMenu::with(['parent', 'roles'])
            ->orderBy('order_no')
            ->get()
            ->map(function ($menu) {
                return [
                    'sidebar_menu_id' => $menu->sidebar_menu_id,
                    'menu_name' => $menu->menu_name,
                    'route_name' => $menu->route_name,
                    'url' => $menu->url,
                    'icon_class' => $menu->icon_class,
                    'parent_id' => $menu->parent_id,
                    'parent_name' => $menu->parent ? $menu->parent->menu_name : null,
                    'order_no' => $menu->order_no,
                    'is_active' => $menu->is_active,
                    'route_params' => $menu->route_params ?? [],
                    'roles' => $menu->roles->map(function ($role) {
                        return [
                            'id' => $role->rid,
                            'name' => $role->name,
                        ];
                    })->toArray(),
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => $menus
        ]);
    }

    /**
     * Store a new menu
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'menu_name' => 'required|string|max:255',
            'route_name' => 'nullable|string|max:255',
            'url' => 'nullable|string|max:500',
            'icon_class' => 'nullable|string|max:100',
            'parent_id' => 'nullable|exists:housing_sidebar_menus,sidebar_menu_id',
            'order_no' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'route_params' => 'nullable',
            'roles' => 'required|array|min:1',
            'roles.*' => 'exists:roles,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Normalize route_params - ensure it's an array or object
            $routeParams = $request->route_params;
            if ($routeParams !== null) {
                // If it's already an array/object, use it; otherwise try to parse
                if (!is_array($routeParams)) {
                    $routeParams = json_decode(json_encode($routeParams), true);
                }
            }
            
            $menu = HousingSidebarMenu::create([
                'menu_name' => $request->menu_name,
                'route_name' => $request->route_name,
                'url' => $request->url,
                'icon_class' => $request->icon_class,
                'parent_id' => $request->parent_id,
                'order_no' => $request->order_no ?? 0,
                'is_active' => $request->is_active ?? true,
                'route_params' => $routeParams,
            ]);

            // Attach roles
            $menu->roles()->sync($request->roles);

            DB::commit();

            $menu->load('roles', 'parent');

            return response()->json([
                'status' => 'success',
                'message' => 'Menu created successfully',
                'data' => $menu
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            ErrorLogService::logException($e, 'error', ['module' => 'sidebar_menus', 'action' => 'create']);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create menu: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a menu
     */
    public function update(Request $request, $id)
    {
        $menu = HousingSidebarMenu::find($id);

        if (!$menu) {
            return response()->json([
                'status' => 'error',
                'message' => 'Menu not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'menu_name' => 'required|string|max:255',
            'route_name' => 'nullable|string|max:255',
            'url' => 'nullable|string|max:500',
            'icon_class' => 'nullable|string|max:100',
            'parent_id' => 'nullable|exists:housing_sidebar_menus,sidebar_menu_id',
            'order_no' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'route_params' => 'nullable',
            'roles' => 'required|array|min:1',
            'roles.*' => 'exists:roles,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Prevent circular reference
        if ($request->parent_id == $id) {
            return response()->json([
                'status' => 'error',
                'message' => 'A menu cannot be its own parent'
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Normalize route_params - ensure it's an array or object
            $routeParams = $request->has('route_params') ? $request->route_params : $menu->route_params;
            if ($routeParams !== null) {
                // If it's already an array/object, use it; otherwise try to parse
                if (!is_array($routeParams)) {
                    $routeParams = json_decode(json_encode($routeParams), true);
                }
            }
            
            $menu->update([
                'menu_name' => $request->menu_name,
                'route_name' => $request->route_name,
                'url' => $request->url,
                'icon_class' => $request->icon_class,
                'parent_id' => $request->parent_id,
                'order_no' => $request->order_no ?? $menu->order_no,
                'is_active' => $request->is_active ?? $menu->is_active,
                'route_params' => $routeParams,
            ]);

            // Sync roles
            $menu->roles()->sync($request->roles);

            DB::commit();

            $menu->load('roles', 'parent');

            return response()->json([
                'status' => 'success',
                'message' => 'Menu updated successfully',
                'data' => $menu
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            ErrorLogService::logException($e, 'error', ['module' => 'sidebar_menus', 'action' => 'update', 'sidebar_menu_id' => $id]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update menu: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a menu
     */
    public function destroy($id)
    {
        $menu = HousingSidebarMenu::find($id);

        if (!$menu) {
            return response()->json([
                'status' => 'error',
                'message' => 'Menu not found'
            ], 404);
        }

        // Check if menu has children
        if ($menu->children()->count() > 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete menu with submenus. Please delete submenus first.'
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Detach roles
            $menu->roles()->detach();
            
            // Delete menu
            $menu->delete();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Menu deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            ErrorLogService::logException($e, 'error', ['module' => 'sidebar_menus', 'action' => 'delete', 'sidebar_menu_id' => $id]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete menu: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single menu
     */
    public function show($id)
    {
        $menu = HousingSidebarMenu::with(['roles', 'parent'])->find($id);

        if (!$menu) {
            return response()->json([
                'status' => 'error',
                'message' => 'Menu not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $menu
        ]);
    }
}

