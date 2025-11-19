<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CmsFileController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('cms/{type}/{id}/{token}', [CmsFileController::class, 'showFileWithId'])->name('cms.files.show_with_id');
Route::get('cms/{type}/{token}', [CmsFileController::class, 'showFile'])->name('cms.files.show');
