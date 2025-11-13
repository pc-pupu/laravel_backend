<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\StaticpageController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\ErrorLogController;

// Public routes
Route::get('/content/{param}', [StaticpageController::class, 'index']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Admin routes
    Route::prefix('admin')->group(function () {
        // Users
        Route::apiResource('users', UserController::class);
        
        // Roles
        Route::apiResource('roles', RoleController::class);
        Route::get('roles/{id}/permissions', [RoleController::class, 'getPermissions']);
        
        // Permissions
        Route::apiResource('permissions', PermissionController::class);
        Route::get('permissions/modules/list', [PermissionController::class, 'getModules']);
        
        // Error Logs
        Route::get('error-logs', [ErrorLogController::class, 'index']);
        Route::get('error-logs/statistics', [ErrorLogController::class, 'statistics']);
        Route::get('error-logs/{id}', [ErrorLogController::class, 'show']);
        Route::delete('error-logs/{id}', [ErrorLogController::class, 'destroy']);
        Route::delete('error-logs', [ErrorLogController::class, 'clear']);
    });
});
