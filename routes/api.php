<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\StaticpageController;

Route::get('/users', [UserController::class, 'index']);

Route::get('/content/{param}', [StaticpageController::class, 'index']);
