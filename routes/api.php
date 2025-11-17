<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\ErrorLogController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\CmsContentController;
use App\Http\Controllers\Api\CmsContentPublicController;
use App\Http\Controllers\Api\SidebarMenuController;

// Public routes
Route::get('/content/{param}', [CmsContentPublicController::class, 'show']);
Route::get('/cms/{param}', [CmsContentPublicController::class, 'show']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Sidebar menus (for authenticated users)
    Route::get('sidebar-menus', [SidebarMenuController::class, 'index']);
    
    Route::prefix('admin')->group(function () {

        // Users
        Route::get('users', [UserController::class, 'index']);
        Route::post('users', [UserController::class, 'store']);
        Route::get('users/{id}', [UserController::class, 'show']);
        Route::put('users/{id}', [UserController::class, 'update']);
        Route::delete('users/{id}', [UserController::class, 'destroy']);

        // Roles
        Route::get('roles', [RoleController::class, 'index']);
        Route::post('roles', [RoleController::class, 'store']);
        Route::get('roles/{id}', [RoleController::class, 'show']);
        Route::put('roles/{id}', [RoleController::class, 'update']);
        Route::delete('roles/{id}', [RoleController::class, 'destroy']);

        // Permissions
        Route::get('permissions', [PermissionController::class, 'index']);
        Route::post('permissions', [PermissionController::class, 'store']);
        Route::get('permissions/{id}', [PermissionController::class, 'show']);
        Route::put('permissions/{id}', [PermissionController::class, 'update']);
        Route::delete('permissions/{id}', [PermissionController::class, 'destroy']);

        // CMS Content
        Route::get('cms-content', [CmsContentController::class, 'index']);
        Route::get('cms-content/meta/stats', [CmsContentController::class, 'stats']);
        Route::post('cms-content', [CmsContentController::class, 'store']);
        Route::get('cms-content/{id}', [CmsContentController::class, 'show']);
        Route::put('cms-content/{id}', [CmsContentController::class, 'update']);
        Route::delete('cms-content/{id}', [CmsContentController::class, 'destroy']);

        // Sidebar Menus Management
        Route::get('sidebar-menus/all', [SidebarMenuController::class, 'all']);
        Route::post('sidebar-menus', [SidebarMenuController::class, 'store']);
        Route::get('sidebar-menus/{id}', [SidebarMenuController::class, 'show']);
        Route::put('sidebar-menus/{id}', [SidebarMenuController::class, 'update']);
        Route::delete('sidebar-menus/{id}', [SidebarMenuController::class, 'destroy']);
    });
});


 