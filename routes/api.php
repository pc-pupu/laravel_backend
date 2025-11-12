<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\StaticpageController;
use App\Http\Controllers\Api\AuthController;

Route::get('/users', [UserController::class, 'index']);

Route::get('/content/{param}', [StaticpageController::class, 'index']);

Route::post('/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
});
