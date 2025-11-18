<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CmsFileController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('cms/{type}/{token}', [CmsFileController::class, 'download'])->name('cms.files.download');
