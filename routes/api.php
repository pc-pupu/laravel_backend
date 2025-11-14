<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\StaticpageController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\ErrorLogController;
use App\Http\Controllers\Api\DashboardController;

// Public routes
Route::get('/content/{param}', [StaticpageController::class, 'index']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
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
    });
});


 